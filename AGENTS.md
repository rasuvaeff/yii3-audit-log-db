# AGENTS.md — yii3-audit-log-db

Guidance for AI agents working on this package. Read before changing code.

## What this is

`rasuvaeff/yii3-audit-log-db` is the persistent DB writer adapter for
`rasuvaeff/yii3-audit-log`. It binds `AuditWriter` to `DbAuditWriter`, which
inserts every `AuditEvent` as a row into the `audit_log` table using any
`yiisoft/db`-compatible driver (SQLite, MySQL, PostgreSQL, etc.).

Namespace: `Rasuvaeff\Yii3AuditLogDb`.

Public API:
- `DbAuditWriter` — implements `AuditWriter`; constructor accepts `ConnectionInterface $db`
  and optional `string $table = 'audit_log'` (validated by `AuditLogTableName`).
- `AuditLogTableName` — the table name as a type; also derives the index-name base.
- `Migration\M260620000000CreateAuditLogTable` — creates the table with
  subject/actor/occurred_at indexes; takes `AuditLogTableName`.

Internal:
- `AuditEventSerializer` — maps `AuditEvent` → row array; `changes` column is JSON.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **Append-only semantics.** `DbAuditWriter::write()` only INSERTs — never
   updates or deletes. Audit rows are immutable once written.
4. **Preserve the public contract.** Update README + tests with any API change.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.
Mount the monorepo root (needed for the path repo to `../yii3-audit-log`):

```bash
# from /home/rasuvaeff/projects/rasuvaeff/
docker run --rm -v "$PWD":/repo -w /repo/yii3-audit-log-db composer:2 composer install
docker run --rm -v "$PWD":/repo -w /repo/yii3-audit-log-db composer:2 composer build
docker run --rm -v "$PWD":/repo -w /repo/yii3-audit-log-db composer:2 composer cs:fix
docker run --rm -v "$PWD":/repo -w /repo/yii3-audit-log-db composer:2 composer psalm
docker run --rm -v "$PWD":/repo -w /repo/yii3-audit-log-db composer:2 composer test
```

Or with Make (from the package directory):

```bash
make install && make build
make cs-fix
make psalm
make test
make test-coverage
make mutation
make release-check
```

`make test-coverage` and `make mutation` bootstrap `pcov` inside the container.

## Invariants & gotchas

- `changes` column stores a JSON array: `[{"field":"x","old":…,"new":…}]`. Old/new
  can be any JSON-encodable value including `null`.
- `occurred_at` is stored as `Y-m-d H:i:s` UTC regardless of the input timezone.
- **The table name is a VO, not a string, because `Injector` cannot resolve a
  scalar.** `yiisoft/db-migration` builds migrations via `Injector::make()`,
  which resolves arguments by name or by type from the container and never reads
  a container definition keyed by the migration's own class. That is why the
  1.x recipe `M...::class => ['__construct()' => ['table' => …]]` silently did
  nothing — and why adding it made `Yiisoft\Di\Container` fatal at build time
  (the global class was not autoloadable until the runner required the file).
  Never reintroduce a scalar `string $table` on a migration.
- **One source of truth for the name.** `config/di.php` builds
  `AuditLogTableName` from `table_prefix` + `table` params and passes it to both
  the writer and the migration. In 1.x the writer read params while the
  migration used its own default, so configuring params produced a writer
  pointing at a table the migration had never created.
- **Index names are derived from the table name** (`idx_<table>_subject`, …),
  with `.` flattened to `_`. In PostgreSQL index names are unique per schema,
  not per table, so hard-coded names collide between two installations sharing
  a schema.
- Table name validated against `/^[A-Za-z_]\w*(\.[A-Za-z_]\w*)?\z/` — `\z`,
  not `$`: PCRE's `$` also matches before a trailing newline, so `"audit_log\n"`
  passed the 1.x check. Schema-qualified names like `public.audit_log` are allowed.
- Migrations live in `src/Migration/` and are therefore covered by cs, psalm and
  infection like any other source file. `MigrationTableNameTest` asserts the
  column set and each index's columns — without that, `ArrayItemRemoval` mutants
  in `createTable`/`createIndex` escape and the MSI gate fails.
- DI rule: this package binds `AuditWriter::class` → `DbAuditWriter`. Never bind it
  in the core package (`yii3-audit-log`). Two backends active simultaneously would
  trigger a Duplicate key error from `yiisoft/config`.
- `AuditEventSerializer` is `@internal` — test through `DbAuditWriter` or the
  integration test, not as public API.
- `repositories` section in `composer.json` uses a path repo to `../yii3-audit-log`
  for local development. Remove it (and the `"version"` field from core's
  `composer.json`) when publishing both packages via Packagist.
- Code: `declare(strict_types=1)`, `final readonly class`, `#[\Override]`,
  explicit types.

## When you finish

- Update `README.md` (and `examples/` if usage changed); update `CHANGELOG.md`
  when releasing.
- Re-run `composer build` (from monorepo root with `-v "$PWD":/repo -w /repo/yii3-audit-log-db`);
  if the change affects public API or release safety, also run `make release-check`. Paste the output.
