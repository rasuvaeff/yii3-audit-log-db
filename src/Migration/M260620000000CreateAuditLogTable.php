<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AuditLogDb\Migration;

use Rasuvaeff\Yii3AuditLogDb\AuditLogTableName;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Creates the audit table used by {@see \Rasuvaeff\Yii3AuditLogDb\DbAuditWriter}.
 *
 * The table name comes from {@see AuditLogTableName}, which `config/di.php`
 * builds from the `rasuvaeff/yii3-audit-log-db` params — one source of truth
 * for the migration and the writer alike. Register the migration by namespace:
 *
 * ```php
 * MigrationService::class => [
 *     'setSourceNamespaces()' => [['Rasuvaeff\\Yii3AuditLogDb\\Migration']],
 * ],
 * ```
 *
 * @api
 */
final class M260620000000CreateAuditLogTable implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function __construct(
        private readonly AuditLogTableName $table = new AuditLogTableName(),
    ) {}

    #[\Override]
    public function up(MigrationBuilder $b): void
    {
        $table = $this->table->value;
        $index = $this->table->forIndexName();

        $b->createTable($table, [
            'id' => 'string(32) NOT NULL PRIMARY KEY',
            'actor_type' => 'string(32) NOT NULL',
            'actor_id' => 'string(255)',
            'actor_name' => 'string(255)',
            'action' => 'string(64) NOT NULL',
            'subject_type' => 'string(255) NOT NULL',
            'subject_id' => 'string(255) NOT NULL',
            'changes' => 'text NOT NULL',
            'occurred_at' => 'string(30) NOT NULL',
            'request_id' => 'string(255)',
            'ip' => 'string(45)',
            'user_agent' => 'text',
        ]);

        // index names follow the table name: in PostgreSQL they are unique per
        // schema, so two installations sharing one schema would collide on a
        // hard-coded name
        $b->createIndex($table, sprintf('idx_%s_subject', $index), ['subject_type', 'subject_id', 'occurred_at']);
        $b->createIndex($table, sprintf('idx_%s_actor', $index), ['actor_type', 'actor_id', 'occurred_at']);
        $b->createIndex($table, sprintf('idx_%s_occurred_at', $index), 'occurred_at');
    }

    #[\Override]
    public function down(MigrationBuilder $b): void
    {
        $b->dropTable($this->table->value);
    }
}
