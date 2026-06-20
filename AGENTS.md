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
  and optional `string $table = 'audit_log'` (validated against identifier regex).

Internal:
- `AuditEventSerializer` — maps `AuditEvent` → row array; `changes` column is JSON.

Migration:
- `migrations/M260620000000CreateAuditLogTable.php` — creates `audit_log` with
  subject/actor/occurred_at indexes; accepts custom table name via constructor.

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
- Table name validated against `/^[A-Za-z_]\w*(\.[A-Za-z_]\w*)?$/`; schema-qualified
  names like `public.audit_log` are allowed.
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
