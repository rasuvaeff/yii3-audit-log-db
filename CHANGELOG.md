# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

- Docs: the documented `setSourceNamespaces()` migration registration does not
  find the bundled migration and never has — `yiisoft/db-migration` matches the
  PSR-4 map by string prefix and resolves into the core package, so
  `./yii migrate:up` exits 0 having created nothing. Both READMEs now say so and
  give a working `Injector`-based recipe until the upstream fix ships.

## 2.0.1 — 2026-07-25

- Document the exact Composer Dependency Analyser exclusion required when this
  package is consumed only through yiisoft/config metadata.

## 2.0.0 — 2026-07-25

**Breaking.** See [UPGRADE.md](UPGRADE.md) — an installation that already
applied the migration must rewrite one row in the `migration` table.

- The bundled migration moved to `Rasuvaeff\Yii3AuditLogDb\Migration\M260620000000CreateAuditLogTable`
  (`src/Migration/`, PSR-4 autoloaded) from a global class in `migrations/`.
  Register it with `setSourceNamespaces()` instead of a `vendor/` path. Being
  autoloadable is what makes it safe to reference in DI at all: with the old
  global class, adding any container definition for it made
  `Yiisoft\Di\Container` fatal at build time in every request, because
  `new ReflectionClass()` ran before the migration runner had required the file.
- **The documented way to rename the table never worked.**
  `M...::class => ['__construct()' => ['table' => ...]]` is ignored:
  `yiisoft/db-migration` builds migrations through `Injector::make()`, which
  resolves arguments by name or type from the container and does not read
  definitions keyed by the migration's class — and a scalar `string $table` has
  no type to resolve. Users following the README silently got the default name.
- The table name is now `AuditLogTableName`, a typed value object that `Injector`
  *can* resolve, built by `config/di.php` from params. One source of truth: the
  migration and `DbAuditWriter` cannot disagree any more (in 1.x the writer read
  params while the migration used its own default, so configuring params gave a
  writer pointing at a table the migration never created).
- New `table_prefix` param, prepended to `table` — a single place to keep
  package tables out of the way of an application's own.
- Index names are derived from the table name (`idx_<table>_subject`, …).
  Unchanged for the default table name; in PostgreSQL, where index names are
  unique per schema rather than per table, hard-coded names collided between two
  installations sharing a schema.
- `AuditLogTableName` validates with `\z`, not `$` — PCRE's `$` also matches
  before a trailing newline, so `"audit_log\n"` passed the 1.x identifier check
  in `DbAuditWriter`.

## 1.0.1 — 2026-06-27

- Migrate test suite from PHPUnit to Testo. Internal change, no public API impact.

## 1.0.0 — 2026-06-20

- `DbAuditWriter` — persistent `AuditWriter` implementation backed by any `yiisoft/db` driver.
- `AuditEventSerializer` — internal serializer: maps `AuditEvent` fields to a DB row array.
- Migration `M260620000000CreateAuditLogTable` with subject/actor/occurred_at indexes.
- Yii3 config-plugin wiring: binds `AuditWriter` to `DbAuditWriter`.
