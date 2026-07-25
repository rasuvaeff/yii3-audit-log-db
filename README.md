# rasuvaeff/yii3-audit-log-db

[![Stable Version](https://poser.pugx.org/rasuvaeff/yii3-audit-log-db/v/stable)](https://packagist.org/packages/rasuvaeff/yii3-audit-log-db)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/yii3-audit-log-db/downloads)](https://packagist.org/packages/rasuvaeff/yii3-audit-log-db)
[![Build](https://github.com/rasuvaeff/yii3-audit-log-db/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/yii3-audit-log-db/actions)
[![Static analysis](https://github.com/rasuvaeff/yii3-audit-log-db/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/yii3-audit-log-db/actions)
[![Psalm Level](https://shepherd.dev/github/rasuvaeff/yii3-audit-log-db/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-audit-log-db)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-audit-log-db/php)](https://packagist.org/packages/rasuvaeff/yii3-audit-log-db)
[![License](https://poser.pugx.org/rasuvaeff/yii3-audit-log-db/license)](https://packagist.org/packages/rasuvaeff/yii3-audit-log-db)
[Русская версия](README.ru.md)

Database-backed `AuditWriter` for [`rasuvaeff/yii3-audit-log`](https://github.com/rasuvaeff/yii3-audit-log).
Persists audit events to any `yiisoft/db`-compatible database (SQLite, MySQL, PostgreSQL, etc.).

> Using an AI coding assistant? [llms.txt](llms.txt) has a compact API reference you can use.

## Requirements

- PHP 8.3+
- `rasuvaeff/yii3-audit-log` ^1.0
- `yiisoft/db` ^2.0

## Installation

```bash
composer require rasuvaeff/yii3-audit-log rasuvaeff/yii3-audit-log-db
```

## Migration

Register the bundled migration **by namespace** — no vendor paths:

```php
// config/common/di/migration.php
use Yiisoft\Db\Migration\Service\MigrationService;

return [
    MigrationService::class => [
        'setSourceNamespaces()' => [[
            'App\\Migration',
            'Rasuvaeff\\Yii3AuditLogDb\\Migration',
        ]],
    ],
];
```

```bash
./yii migrate:up
```

### Custom table name

Set it in params — the same value reaches the migration **and** the writer:

```php
// config/common/params.php
'rasuvaeff/yii3-audit-log-db' => [
    'table' => 'my_audit_log',
    'table_prefix' => '',      // prepended to `table`; e.g. 'rsv_' → rsv_my_audit_log
],
```

Index names follow the table name (`idx_my_audit_log_subject`, …), so two
installations can share one PostgreSQL schema — index names are unique per
schema there, not per table.

> **Do not configure the migration through the DI container.**
> `M...::class => ['__construct()' => ['table' => ...]]` does not work: the
> migration is built by `Injector::make()`, which resolves arguments by type
> and never reads a container definition keyed by the migration's own class.
> Worse, adding that definition makes the container fatal at build time in
> **every** request, because the class is not autoloadable until the migration
> runner requires it. That recipe was documented in 1.x; it never worked.

The migration creates:

| Column | Type | Notes |
|---|---|---|
| `id` | VARCHAR(32) PK | 32-char hex from `AuditLogger` |
| `actor_type` | VARCHAR(32) | `user` / `system` |
| `actor_id` | VARCHAR(255) NULL | null for system actor |
| `actor_name` | VARCHAR(255) NULL | display name, optional |
| `action` | VARCHAR(64) | `create` / `update` / `delete` / custom |
| `subject_type` | VARCHAR(255) | entity type, e.g. `order` |
| `subject_id` | VARCHAR(255) | entity id |
| `changes` | TEXT | JSON array of `{field, old, new}` objects |
| `occurred_at` | VARCHAR(30) | `Y-m-d H:i:s` UTC |
| `request_id` | VARCHAR(255) NULL | from `AuditMetadata` |
| `ip` | VARCHAR(45) NULL | from `AuditMetadata` |
| `user_agent` | TEXT NULL | from `AuditMetadata` |

Indexes: `(subject_type, subject_id, occurred_at)`, `(actor_type, actor_id, occurred_at)`, `(occurred_at)`.

## Yii3 config-plugin

Install `rasuvaeff/yii3-audit-log` (core) and `rasuvaeff/yii3-audit-log-db` (adapter).
The adapter's `config/di.php` automatically binds `AuditWriter` to `DbAuditWriter`.

The core's `config/di.php` wires `AuditLogger`. You only need to bind `ClockInterface`
in your application config:

```php
// config/common/di/clock.php
use Psr\Clock\ClockInterface;

return [
    ClockInterface::class => MySystemClock::class,
];
```

Custom table name via params:

```php
// config/common/params.php
return [
    'rasuvaeff/yii3-audit-log-db' => [
        'table' => 'my_audit_log',
    ],
];
```

## Usage

### Write via AuditLogger

```php
use Rasuvaeff\Yii3AuditLog\AuditActor;
use Rasuvaeff\Yii3AuditLog\AuditChangeSet;
use Rasuvaeff\Yii3AuditLog\AuditLogger;
use Rasuvaeff\Yii3AuditLog\AuditSubject;

// Injected via DI:
/** @var AuditLogger $logger */

$logger->logChange(
    actor: AuditActor::user(id: (string) $user->id, name: $user->name),
    subject: AuditSubject::of(type: 'order', id: (string) $order->id),
    changes: AuditChangeSet::fromArrays(old: $before, new: $after),
);
```

### Use directly

```php
use Rasuvaeff\Yii3AuditLogDb\DbAuditWriter;

$writer = new DbAuditWriter(db: $db, table: 'audit_log');
$writer->write(event: $auditEvent);
```

## Security

- `DbAuditWriter` validates the table name against `/^[A-Za-z_]\w*(\.[A-Za-z_]\w*)?$/` — schema-qualified names like `public.audit_log` are allowed; arbitrary strings are rejected.
- All event field values are passed as bound parameters via `yiisoft/db` — no SQL injection risk.
- `changes` values are whatever `AuditChangeSet` contains. Apply `SensitiveValueMasker` in `AuditLogger` before this writer runs (default in core DI).

## Examples

See [`examples/`](examples/) for runnable scripts.

## Development

```bash
# from monorepo root (/home/rasuvaeff/projects/rasuvaeff)
make -C yii3-audit-log-db install
make -C yii3-audit-log-db build
make -C yii3-audit-log-db cs-fix
make -C yii3-audit-log-db test
```

Or with Docker directly:

```bash
docker run --rm -v "$PWD":/repo -w /repo/yii3-audit-log-db composer:2 composer build
```

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
