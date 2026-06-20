<?php

declare(strict_types=1);

use Rasuvaeff\Yii3AuditLog\AuditWriter;
use Rasuvaeff\Yii3AuditLogDb\DbAuditWriter;
use Yiisoft\Db\Connection\ConnectionInterface;

/** @var array $params */

return [
    AuditWriter::class => static function (ConnectionInterface $db) use ($params): DbAuditWriter {
        $config = $params['rasuvaeff/yii3-audit-log-db'] ?? [];

        return new DbAuditWriter(
            db: $db,
            table: $config['table'] ?? 'audit_log',
        );
    },
];
