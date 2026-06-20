<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AuditLogDb\Tests\Integration;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Rasuvaeff\Yii3AuditLog\AuditActor;
use Rasuvaeff\Yii3AuditLog\AuditChangeSet;
use Rasuvaeff\Yii3AuditLog\AuditEvent;
use Rasuvaeff\Yii3AuditLog\AuditLogger;
use Rasuvaeff\Yii3AuditLog\AuditMetadata;
use Rasuvaeff\Yii3AuditLog\AuditSubject;
use Rasuvaeff\Yii3AuditLogDb\DbAuditWriter;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;
use Yiisoft\Test\Support\Clock\StaticClock;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

#[CoversNothing]
final class SqliteIntegrationTest extends TestCase
{
    private ConnectionInterface $db;

    #[\Override]
    protected function setUp(): void
    {
        $driver = new SqliteDriver(dsn: 'sqlite::memory:');
        $schemaCache = new SchemaCache(psrCache: new MemorySimpleCache());
        $this->db = new SqliteConnection(driver: $driver, schemaCache: $schemaCache);
        $this->db->open();
        $this->createTable();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->db->close();
    }

    #[Test]
    public function writesEventToDatabase(): void
    {
        $writer = new DbAuditWriter(db: $this->db);
        $event = $this->event(id: 'ev-001');

        $writer->write(event: $event);

        $rows = (new Query($this->db))->from('audit_log')->all();
        $this->assertCount(1, $rows);
        $this->assertSame('ev-001', $rows[0]['id']);
    }

    #[Test]
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
        $this->assertNotNull($row);
        $this->assertSame('user', $row['actor_type']);
        $this->assertSame('u1', $row['actor_id']);
        $this->assertSame('Alice', $row['actor_name']);
        $this->assertSame('update', $row['action']);
        $this->assertSame('order', $row['subject_type']);
        $this->assertSame('42', $row['subject_id']);
        $this->assertSame('2026-06-20 10:00:00', $row['occurred_at']);
        $this->assertSame('req-1', $row['request_id']);
        $this->assertSame('192.168.1.1', $row['ip']);
        $this->assertSame('Agent/1', $row['user_agent']);
    }

    #[Test]
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
        $this->assertNotNull($row);
        /** @var list<array{field: string, old: mixed, new: mixed}> $changes */
        $changes = json_decode((string) $row['changes'], associative: true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(1, $changes);
        $this->assertSame('status', $changes[0]['field']);
        $this->assertSame('active', $changes[0]['old']);
        $this->assertSame('banned', $changes[0]['new']);
    }

    #[Test]
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
        $this->assertNotNull($row);
        $this->assertSame('system', $row['actor_type']);
        $this->assertNull($row['actor_id']);
        $this->assertNull($row['actor_name']);
    }

    #[Test]
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
        $this->assertNotNull($row);
        $this->assertNull($row['request_id']);
        $this->assertNull($row['ip']);
        $this->assertNull($row['user_agent']);
    }

    #[Test]
    public function writesMultipleEventsIndependently(): void
    {
        $writer = new DbAuditWriter(db: $this->db);

        $writer->write(event: $this->event(id: 'a'));
        $writer->write(event: $this->event(id: 'b'));
        $writer->write(event: $this->event(id: 'c'));

        $count = (new Query($this->db))->from('audit_log')->count();
        $this->assertSame(3, (int) $count);
    }

    #[Test]
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
        $this->assertSame(1, (int) $count);
    }

    #[Test]
    public function supportsCustomTableName(): void
    {
        $this->db->createCommand(sql: 'CREATE TABLE custom_audit (id VARCHAR(32) PRIMARY KEY, actor_type VARCHAR(32) NOT NULL, actor_id VARCHAR(255), actor_name VARCHAR(255), action VARCHAR(64) NOT NULL, subject_type VARCHAR(255) NOT NULL, subject_id VARCHAR(255) NOT NULL, changes TEXT NOT NULL, occurred_at VARCHAR(30) NOT NULL, request_id VARCHAR(255), ip VARCHAR(45), user_agent TEXT)')->execute();

        $writer = new DbAuditWriter(db: $this->db, table: 'custom_audit');
        $writer->write(event: $this->event(id: 'custom-1'));

        $count = (new Query($this->db))->from('custom_audit')->count();
        $this->assertSame(1, (int) $count);
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
