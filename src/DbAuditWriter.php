<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AuditLogDb;

use InvalidArgumentException;
use Rasuvaeff\Yii3AuditLog\AuditEvent;
use Rasuvaeff\Yii3AuditLog\AuditWriter;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * @api
 */
final readonly class DbAuditWriter implements AuditWriter
{
    private const string TABLE_PATTERN = '/^[A-Za-z_]\w*(\.[A-Za-z_]\w*)?$/';

    /**
     * @param non-empty-string $table
     */
    public function __construct(
        private ConnectionInterface $db,
        private string $table = 'audit_log',
    ) {
        if (preg_match(self::TABLE_PATTERN, $table) !== 1) {
            throw new InvalidArgumentException('Invalid table name "' . $table . '"');
        }
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
