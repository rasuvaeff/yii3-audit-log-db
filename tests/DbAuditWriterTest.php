<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AuditLogDb\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use Rasuvaeff\Yii3AuditLog\AuditActor;
use Rasuvaeff\Yii3AuditLog\AuditChangeSet;
use Rasuvaeff\Yii3AuditLog\AuditEvent;
use Rasuvaeff\Yii3AuditLog\AuditSubject;
use Rasuvaeff\Yii3AuditLogDb\DbAuditWriter;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

#[Test]
#[Covers(DbAuditWriter::class)]
final class DbAuditWriterTest
{
    public function acceptsDefaultTableName(): void
    {
        $writer = new DbAuditWriter(db: new FakeConnection());
        Assert::instanceOf($writer, DbAuditWriter::class);
    }

    public function writeInsertsTheSerializedEventRow(): void
    {
        $db = new SqliteConnection(
            driver: new SqliteDriver(dsn: 'sqlite::memory:'),
            schemaCache: new SchemaCache(psrCache: new MemorySimpleCache()),
        );
        $db->createCommand(sql: 'CREATE TABLE my_audit (id VARCHAR(32) PRIMARY KEY, actor_type VARCHAR(32) NOT NULL, actor_id VARCHAR(255), actor_name VARCHAR(255), action VARCHAR(64) NOT NULL, subject_type VARCHAR(255) NOT NULL, subject_id VARCHAR(255) NOT NULL, changes TEXT NOT NULL, occurred_at VARCHAR(30) NOT NULL, request_id VARCHAR(255), ip VARCHAR(45), user_agent TEXT)')->execute();
        $event = new AuditEvent(
            id: 'ev-1',
            actor: AuditActor::system(),
            action: 'create',
            subject: AuditSubject::of(type: 'order', id: '7'),
            changeSet: AuditChangeSet::empty(),
            occurredAt: new DateTimeImmutable('2026-06-20 10:00:00 UTC'),
        );

        (new DbAuditWriter(db: $db, table: 'my_audit'))->write(event: $event);

        $rows = (new Query($db))->from('my_audit')->all();
        Assert::count($rows, 1);
        Assert::same($rows[0]['id'], 'ev-1');
        Assert::same($rows[0]['subject_id'], '7');
    }

    public function exceptionMessageContainsInvalidTableName(): void
    {
        try {
            new DbAuditWriter(db: new FakeConnection(), table: '0invalid');
            Assert::fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('"0invalid"');
        }
    }

    public function acceptsCustomTableName(): void
    {
        $writer = new DbAuditWriter(
            db: new FakeConnection(),
            table: 'my_audit_log',
        );
        Assert::instanceOf($writer, DbAuditWriter::class);
    }

    public function acceptsSchemaQualifiedTableName(): void
    {
        $writer = new DbAuditWriter(
            db: new FakeConnection(),
            table: 'public.audit_log',
        );
        Assert::instanceOf($writer, DbAuditWriter::class);
    }

    #[DataProvider('invalidTableNameProvider')]
    public function throwsOnInvalidTableName(string $table): void
    {
        Expect::exception(InvalidArgumentException::class);

        new DbAuditWriter(
            db: new FakeConnection(),
            table: $table,
        );
    }

    public static function invalidTableNameProvider(): iterable
    {
        yield 'starts with digit' => ['0audit_log'];
        yield 'space in name' => ['audit log'];
        yield 'semicolon injection' => ['audit_log; DROP TABLE users'];
        yield 'dash in name' => ['audit-log'];
        yield 'empty string' => [''];
    }
}
