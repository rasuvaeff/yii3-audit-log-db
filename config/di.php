<?php

declare(strict_types=1);

use Rasuvaeff\Yii3AuditLog\AuditWriter;
use Rasuvaeff\Yii3AuditLogDb\AuditLogTableName;
use Rasuvaeff\Yii3AuditLogDb\DbAuditWriter;
use Yiisoft\Db\Connection\ConnectionInterface;

/** @var array $params */

return [
    // the migration resolves this by type through Injector::make(), so the
    // writer and the migration can never disagree about the table
    AuditLogTableName::class => static function () use ($params): AuditLogTableName {
        $config = $params['rasuvaeff/yii3-audit-log-db'] ?? [];

        return new AuditLogTableName(
            ((string) ($config['table_prefix'] ?? '')) . ((string) ($config['table'] ?? 'audit_log')),
        );
    },
    AuditWriter::class => static fn (
        ConnectionInterface $db,
        AuditLogTableName $table,
    ): DbAuditWriter => new DbAuditWriter(db: $db, table: $table->value),
];
