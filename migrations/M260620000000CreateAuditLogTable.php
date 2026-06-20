<?php

declare(strict_types=1);

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Creates the audit_log table used by {@see \Rasuvaeff\Yii3AuditLogDb\DbAuditWriter}.
 *
 * To use a custom table name, bind the constructor argument in your DI config:
 *
 * ```php
 * M260620000000CreateAuditLogTable::class => [
 *     '__construct()' => ['table' => 'my_audit_log'],
 * ],
 * ```
 */
final class M260620000000CreateAuditLogTable implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    /**
     * @param non-empty-string $table
     */
    public function __construct(
        private readonly string $table = 'audit_log',
    ) {}

    #[\Override]
    public function up(MigrationBuilder $b): void
    {
        $b->createTable($this->table, [
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

        $b->createIndex($this->table, 'idx_audit_log_subject', ['subject_type', 'subject_id', 'occurred_at']);
        $b->createIndex($this->table, 'idx_audit_log_actor', ['actor_type', 'actor_id', 'occurred_at']);
        $b->createIndex($this->table, 'idx_audit_log_occurred_at', 'occurred_at');
    }

    #[\Override]
    public function down(MigrationBuilder $b): void
    {
        $b->dropTable($this->table);
    }
}
