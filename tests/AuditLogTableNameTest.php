<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AuditLogDb\Tests;

use InvalidArgumentException;
use Rasuvaeff\Yii3AuditLogDb\AuditLogTableName;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(AuditLogTableName::class)]
final class AuditLogTableNameTest
{
    public function defaultsToAuditLog(): void
    {
        Assert::same((new AuditLogTableName())->value, 'audit_log');
        Assert::same((string) new AuditLogTableName(), 'audit_log');
    }

    public function acceptsASchemaQualifiedName(): void
    {
        Assert::same((new AuditLogTableName('public.audit_log'))->value, 'public.audit_log');
    }

    public function indexBaseFlattensTheSchemaSeparator(): void
    {
        // a dot cannot appear in an index name
        Assert::same((new AuditLogTableName('public.audit_log'))->forIndexName(), 'public_audit_log');
        Assert::same((new AuditLogTableName('audit_log'))->forIndexName(), 'audit_log');
    }

    #[DataProvider('invalidNamesProvider')]
    public function rejectsAnythingOutsideTheIdentifierWhitelist(string $name): void
    {
        Expect::exception(InvalidArgumentException::class);

        new AuditLogTableName($name);
    }

    public static function invalidNamesProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'starts with digit' => ['1audit'];
        yield 'space' => ['audit log'];
        yield 'semicolon injection' => ['audit; DROP TABLE users'];
        yield 'dash' => ['audit-log'];
        yield 'two dots' => ['a.b.c'];
        // PCRE's $ also matches before a trailing newline — the pattern is
        // anchored with \z so this is rejected
        yield 'trailing newline' => ["audit_log\n"];
    }
}
