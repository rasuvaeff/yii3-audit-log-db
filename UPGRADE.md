# Upgrade guide

## 1.x → 2.0

The bundled migration moved into the package namespace:

```
M260620000000CreateAuditLogTable
→ Rasuvaeff\Yii3AuditLogDb\Migration\M260620000000CreateAuditLogTable
```

`yiisoft/db-migration` stores the applied migration's class name verbatim in the
`migration` table. Without the two steps below, `migrate:up` sees the namespaced
class as a *new* migration and fails with "table already exists".

### 1. Rewrite the applied migration's name

```sql
UPDATE migration
SET name = 'Rasuvaeff\\Yii3AuditLogDb\\Migration\\M260620000000CreateAuditLogTable'
WHERE name = 'M260620000000CreateAuditLogTable';
```

Run this **before** the first `migrate:up` on 2.0. If you have never applied the
migration, skip it — there is nothing to rename.

### 2. Register by namespace instead of by path

```diff
 MigrationService::class => [
-    'setSourcePaths()' => [[__DIR__ . '/../vendor/rasuvaeff/yii3-audit-log-db/migrations']],
+    'setSourceNamespaces()' => [['Rasuvaeff\\Yii3AuditLogDb\\Migration']],
 ],
```

The path form no longer resolves: `migrations/` is gone and the class lives
under `src/Migration/`, autoloaded via PSR-4.

### 3. Remove any DI definition of the migration

```diff
-M260620000000CreateAuditLogTable::class => [
-    '__construct()' => ['table' => 'my_audit_log'],
-],
```

That recipe was documented in 1.x and **never worked** — the migration is built
by `Injector::make()`, which resolves arguments by type and ignores container
definitions keyed by the migration's class. It also makes the container fatal at
build time in every request, because the class is not autoloadable until the
migration runner requires it.

Set the table name in params instead; the same value now reaches the migration
and `DbAuditWriter`:

```php
'rasuvaeff/yii3-audit-log-db' => [
    'table' => 'my_audit_log',
    'table_prefix' => '',
],
```

### Table and index names are unchanged by default

Defaults still produce `audit_log` with `idx_audit_log_subject`,
`idx_audit_log_actor` and `idx_audit_log_occurred_at`. Index names are now
derived from the table name, so they only change if you change the table name —
no schema migration is required for this release.
