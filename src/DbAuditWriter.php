<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AuditLogDb;

use Rasuvaeff\Yii3AuditLog\AuditEvent;
use Rasuvaeff\Yii3AuditLog\AuditWriter;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * @api
 */
final readonly class DbAuditWriter implements AuditWriter
{
    private string $table;

    /**
     * @param non-empty-string $table
     *
     * @throws \InvalidArgumentException when the name is not a valid identifier
     */
    public function __construct(
        private ConnectionInterface $db,
        string $table = 'audit_log',
    ) {
        // validation lives in the value object, so the writer and the bundled
        // migration cannot disagree about what a valid table name is
        $this->table = (new AuditLogTableName($table))->value;
    }

    #[\Override]
    public function write(AuditEvent $event): void
    {
        $this->db->createCommand()->insert(
            table: $this->table,
            columns: AuditEventSerializer::serialize(event: $event),
        )->execute();
    }
}
