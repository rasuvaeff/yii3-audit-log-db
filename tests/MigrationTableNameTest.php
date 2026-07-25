<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AuditLogDb\Tests;

use Rasuvaeff\Yii3AuditLogDb\AuditLogTableName;
use Rasuvaeff\Yii3AuditLogDb\Migration\M260620000000CreateAuditLogTable;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;
use Yiisoft\Injector\Injector;
use Yiisoft\Test\Support\Container\SimpleContainer;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

/**
 * The migration is created by `yiisoft/db-migration` through `Injector::make()`,
 * not by the container, so a test that instantiates it directly proves nothing
 * about whether configuration actually reaches it. These go through the real
 * resolver.
 */
#[Test]
#[Covers(M260620000000CreateAuditLogTable::class)]
final class MigrationTableNameTest
{
    private ConnectionInterface $db;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->db = new SqliteConnection(
            driver: new SqliteDriver(dsn: 'sqlite::memory:'),
            schemaCache: new SchemaCache(psrCache: new MemorySimpleCache()),
        );
    }

    public function containerBoundTableNameReachesTheMigration(): void
    {
        $migration = $this->make(new SimpleContainer([
            AuditLogTableName::class => new AuditLogTableName('custom_audit'),
        ]));

        $migration->up($this->builder());

        Assert::notNull($this->db->getTableSchema('custom_audit', true));
        Assert::null($this->db->getTableSchema('audit_log', true));
    }

    public function withoutABindingTheDefaultNameIsUsed(): void
    {
        // Injector falls back to the parameter default, so the package stays
        // usable with no configuration at all
        $migration = $this->make(new SimpleContainer([]));

        $migration->up($this->builder());

        Assert::notNull($this->db->getTableSchema('audit_log', true));
    }

    public function indexNamesFollowTheTableName(): void
    {
        // hard-coded index names collide in PostgreSQL, where names are unique
        // per schema rather than per table
        $migration = $this->make(new SimpleContainer([
            AuditLogTableName::class => new AuditLogTableName('custom_audit'),
        ]));

        $migration->up($this->builder());

        $indexes = $this->indexNames('custom_audit');
        Assert::true(in_array('idx_custom_audit_subject', $indexes, true));
        Assert::true(in_array('idx_custom_audit_actor', $indexes, true));
        Assert::true(in_array('idx_custom_audit_occurred_at', $indexes, true));
    }

    public function createsTheDocumentedColumnSet(): void
    {
        // the column list IS the contract with DbAuditWriter/AuditEventSerializer:
        // a column silently dropped here surfaces only as a failing INSERT in
        // production
        $migration = $this->make(new SimpleContainer([]));

        $migration->up($this->builder());

        $schema = $this->db->getTableSchema('audit_log', true);
        Assert::notNull($schema);
        Assert::same(array_keys($schema->getColumns()), [
            'id',
            'actor_type',
            'actor_id',
            'actor_name',
            'action',
            'subject_type',
            'subject_id',
            'changes',
            'occurred_at',
            'request_id',
            'ip',
            'user_agent',
        ]);
    }

    public function indexesCoverTheDocumentedColumns(): void
    {
        $migration = $this->make(new SimpleContainer([]));

        $migration->up($this->builder());

        Assert::same($this->indexColumns('idx_audit_log_subject'), ['subject_type', 'subject_id', 'occurred_at']);
        Assert::same($this->indexColumns('idx_audit_log_actor'), ['actor_type', 'actor_id', 'occurred_at']);
        Assert::same($this->indexColumns('idx_audit_log_occurred_at'), ['occurred_at']);
    }

    public function downDropsTheConfiguredTable(): void
    {
        $migration = $this->make(new SimpleContainer([
            AuditLogTableName::class => new AuditLogTableName('custom_audit'),
        ]));
        $builder = $this->builder();

        $migration->up($builder);
        $migration->down($builder);

        Assert::null($this->db->getTableSchema('custom_audit', true));
    }

    private function make(SimpleContainer $container): M260620000000CreateAuditLogTable
    {
        /** @var M260620000000CreateAuditLogTable */
        return (new Injector($container))->make(M260620000000CreateAuditLogTable::class);
    }

    private function builder(): MigrationBuilder
    {
        return new MigrationBuilder($this->db, new NullMigrationInformer());
    }

    /**
     * @return list<string>
     */
    private function indexColumns(string $index): array
    {
        $columns = [];

        /** @var array<array-key, mixed> $row */
        foreach ($this->db->createCommand(sprintf('PRAGMA index_info(%s)', $index))->queryAll() as $row) {
            if (is_array($row) && is_string($row['name'] ?? null)) {
                $columns[] = $row['name'];
            }
        }

        return $columns;
    }

    /**
     * @return list<string>
     */
    private function indexNames(string $table): array
    {
        $names = [];

        /** @var array<array-key, mixed> $row */
        foreach ($this->db->createCommand(sprintf('PRAGMA index_list(%s)', $table))->queryAll() as $row) {
            if (is_array($row) && is_string($row['name'] ?? null)) {
                $names[] = $row['name'];
            }
        }

        return $names;
    }
}
