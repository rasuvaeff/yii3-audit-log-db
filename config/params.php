<?php

declare(strict_types=1);

return [
    'rasuvaeff/yii3-audit-log-db' => [
        // one source of truth: both DbAuditWriter and the bundled migration
        // read the resulting name through AuditLogTableName
        'table' => 'audit_log',
        // prepended to `table`; set it once to keep every rasuvaeff table out
        // of the way of your application's own
        'table_prefix' => '',
    ],
];
