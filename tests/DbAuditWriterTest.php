<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AuditLogDb\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3AuditLogDb\DbAuditWriter;
use Yiisoft\Db\Connection\ConnectionInterface;

#[CoversClass(DbAuditWriter::class)]
final class DbAuditWriterTest extends TestCase
{
    #[Test]
    public function acceptsDefaultTableName(): void
    {
        $writer = new DbAuditWriter(db: $this->createStub(ConnectionInterface::class));

        $this->assertInstanceOf(DbAuditWriter::class, $writer);
    }

    #[Test]
    public function acceptsCustomTableName(): void
    {
        $writer = new DbAuditWriter(
            db: $this->createStub(ConnectionInterface::class),
            table: 'my_audit_log',
        );

        $this->assertInstanceOf(DbAuditWriter::class, $writer);
    }

    #[Test]
    public function acceptsSchemaQualifiedTableName(): void
    {
        $writer = new DbAuditWriter(
            db: $this->createStub(ConnectionInterface::class),
            table: 'public.audit_log',
        );

        $this->assertInstanceOf(DbAuditWriter::class, $writer);
    }

    #[Test]
    #[DataProvider('invalidTableNameProvider')]
    public function throwsOnInvalidTableName(string $table): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid table name "' . $table . '"');

        new DbAuditWriter(
            db: $this->createStub(ConnectionInterface::class),
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
