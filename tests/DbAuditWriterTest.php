<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AuditLogDb\Tests;

use InvalidArgumentException;
use Rasuvaeff\Yii3AuditLogDb\DbAuditWriter;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(DbAuditWriter::class)]
final class DbAuditWriterTest
{
    public function acceptsDefaultTableName(): void
    {
        $writer = new DbAuditWriter(db: new FakeConnection());
        Assert::instanceOf($writer, DbAuditWriter::class);
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
