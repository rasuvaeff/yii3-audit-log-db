<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AuditLogDb\Tests\Integration;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Rasuvaeff\Yii3AuditLog\AuditActor;
use Rasuvaeff\Yii3AuditLog\AuditChangeSet;
use Rasuvaeff\Yii3AuditLog\AuditEvent;
use Rasuvaeff\Yii3AuditLog\AuditLogger;
use Rasuvaeff\Yii3AuditLog\AuditMetadata;
use Rasuvaeff\Yii3AuditLog\AuditSubject;
use Rasuvaeff\Yii3AuditLogDb\DbAuditWriter;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;
use Yiisoft\Test\Support\Clock\StaticClock;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

#[Test]
#[CoversNothing]
final class SqliteIntegrationTest
{
    private ConnectionInterface $db;

    #[BeforeTest]
    public function setUp(): void
    {
        $driver = new SqliteDriver(dsn: 'sqlite::memory:');
        $schemaCache = new SchemaCache(psrCache: new MemorySimpleCache());
        $this->db = new SqliteConnection(driver: $driver, schemaCache: $schemaCache);
        $this->db->open();
        $this->createTable();
    }

    #[AfterTest]
    public function tearDown(): void
    {
        $this->db->close();
    }

    public function writesEventToDatabase(): void
    {
        $writer = new DbAuditWriter(db: $this->db);
        $event = $this->event(id: 'ev-001');

        $writer->write(event: $event);

        $rows = (new Query($this->db))->from('audit_log')->all();
        Assert::count($rows, 1);
        Assert::same($rows[0]['id'], 'ev-001');
    }

    public function persistsAllScalarColumns(): void
    {
        $writer = new DbAuditWriter(db: $this->db);
        $event = new AuditEvent(
            id: 'ev-002',
            actor: AuditActor::user(id: 'u1', name: 'Alice'),
            action: 'update',
            subject: AuditSubject::of(type: 'order', id: '42'),
            changeSet: AuditChangeSet::empty(),
            occurredAt: new DateTimeImmutable('2026-06-20 10:00:00 UTC'),
            metadata: new AuditMetadata(requestId: 'req-1', ip: '192.168.1.1', userAgent: 'Agent/1'),
        );

        $writer->write(event: $event);

        $row = (new Query($this->db))->from('audit_log')->where(['id' => 'ev-002'])->one();
        Assert::notNull($row);
        Assert::same($row['actor_type'], 'user');
        Assert::same($row['actor_id'], 'u1');
        Assert::same($row['actor_name'], 'Alice');
        Assert::same($row['action'], 'update');
        Assert::same($row['subject_type'], 'order');
        Assert::same($row['subject_id'], '42');
        Assert::same($row['occurred_at'], '2026-06-20 10:00:00');
        Assert::same($row['request_id'], 'req-1');
        Assert::same($row['ip'], '192.168.1.1');
        Assert::same($row['user_agent'], 'Agent/1');
    }

    public function persistsChangesAsJson(): void
    {
        $writer = new DbAuditWriter(db: $this->db);
        $event = new AuditEvent(
            id: 'ev-003',
            actor: AuditActor::system(),
            action: 'update',
            subject: AuditSubject::of(type: 'user', id: '1'),
            changeSet: AuditChangeSet::fromArrays(
                old: ['status' => 'active'],
                new: ['status' => 'banned'],
            ),
            occurredAt: new DateTimeImmutable('2026-06-20 10:00:00 UTC'),
        );

        $writer->write(event: $event);

        $row = (new Query($this->db))->from('audit_log')->where(['id' => 'ev-003'])->one();
        Assert::notNull($row);
        /** @var list<array{field: string, old: mixed, new: mixed}> $changes */
        $changes = json_decode((string) $row['changes'], associative: true, flags: JSON_THROW_ON_ERROR);
        Assert::count($changes, 1);
        Assert::same($changes[0]['field'], 'status');
        Assert::same($changes[0]['old'], 'active');
        Assert::same($changes[0]['new'], 'banned');
    }

    public function persistsSystemActorWithNullIds(): void
    {
        $writer = new DbAuditWriter(db: $this->db);
        $event = new AuditEvent(
            id: 'ev-004',
            actor: AuditActor::system(),
            action: 'create',
            subject: AuditSubject::of(type: 'config', id: 'app'),
            changeSet: AuditChangeSet::empty(),
            occurredAt: new DateTimeImmutable('2026-06-20 10:00:00 UTC'),
        );

        $writer->write(event: $event);

        $row = (new Query($this->db))->from('audit_log')->where(['id' => 'ev-004'])->one();
        Assert::notNull($row);
        Assert::same($row['actor_type'], 'system');
        Assert::null($row['actor_id']);
        Assert::null($row['actor_name']);
    }

    public function persistsNullMetadataColumnsAsNull(): void
    {
        $writer = new DbAuditWriter(db: $this->db);
        $event = new AuditEvent(
            id: 'ev-005',
            actor: AuditActor::system(),
            action: 'delete',
            subject: AuditSubject::of(type: 'item', id: '99'),
            changeSet: AuditChangeSet::empty(),
            occurredAt: new DateTimeImmutable('2026-06-20 10:00:00 UTC'),
        );

        $writer->write(event: $event);

        $row = (new Query($this->db))->from('audit_log')->where(['id' => 'ev-005'])->one();
        Assert::notNull($row);
        Assert::null($row['request_id']);
        Assert::null($row['ip']);
        Assert::null($row['user_agent']);
    }

    public function writesMultipleEventsIndependently(): void
    {
        $writer = new DbAuditWriter(db: $this->db);

        $writer->write(event: $this->event(id: 'a'));
        $writer->write(event: $this->event(id: 'b'));
        $writer->write(event: $this->event(id: 'c'));

        $count = (new Query($this->db))->from('audit_log')->count();
        Assert::same((int) $count, 3);
    }

    public function auditLoggerIntegration(): void
    {
        $writer = new DbAuditWriter(db: $this->db);
        $logger = new AuditLogger(
            writer: $writer,
            clock: $this->fixedClock(),
        );

        $logger->logChange(
            actor: AuditActor::user(id: 'u1'),
            subject: AuditSubject::of(type: 'order', id: '10'),
            changes: AuditChangeSet::fromArrays(
                old: ['total' => 0],
                new: ['total' => 50],
            ),
        );

        $count = (new Query($this->db))->from('audit_log')->count();
        Assert::same((int) $count, 1);
    }

    public function supportsCustomTableName(): void
    {
        $this->db->createCommand(sql: 'CREATE TABLE custom_audit (id VARCHAR(32) PRIMARY KEY, actor_type VARCHAR(32) NOT NULL, actor_id VARCHAR(255), actor_name VARCHAR(255), action VARCHAR(64) NOT NULL, subject_type VARCHAR(255) NOT NULL, subject_id VARCHAR(255) NOT NULL, changes TEXT NOT NULL, occurred_at VARCHAR(30) NOT NULL, request_id VARCHAR(255), ip VARCHAR(45), user_agent TEXT)')->execute();

        $writer = new DbAuditWriter(db: $this->db, table: 'custom_audit');
        $writer->write(event: $this->event(id: 'custom-1'));

        $count = (new Query($this->db))->from('custom_audit')->count();
        Assert::same((int) $count, 1);
    }

    private function event(string $id = 'ev-1'): AuditEvent
    {
        return new AuditEvent(
            id: $id,
            actor: AuditActor::user(id: 'u1'),
            action: 'update',
            subject: AuditSubject::of(type: 'order', id: '1'),
            changeSet: AuditChangeSet::empty(),
            occurredAt: new DateTimeImmutable('2026-06-20 10:00:00 UTC'),
        );
    }

    private function fixedClock(): ClockInterface
    {
        return new StaticClock(new DateTimeImmutable('2026-06-20 10:00:00'));
    }

    private function createTable(): void
    {
        $this->db->createCommand(sql: 'CREATE TABLE audit_log (id VARCHAR(32) PRIMARY KEY, actor_type VARCHAR(32) NOT NULL, actor_id VARCHAR(255), actor_name VARCHAR(255), action VARCHAR(64) NOT NULL, subject_type VARCHAR(255) NOT NULL, subject_id VARCHAR(255) NOT NULL, changes TEXT NOT NULL, occurred_at VARCHAR(30) NOT NULL, request_id VARCHAR(255), ip VARCHAR(45), user_agent TEXT)')->execute();
    }
}
