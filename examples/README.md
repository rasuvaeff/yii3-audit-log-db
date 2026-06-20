# Examples

| Script | Shows | Needs server? |
|---|---|---|
| `basic_usage.php` | Write audit events via `DbAuditWriter` + SQLite in-memory | No |

## Running

```bash
# from /home/rasuvaeff/projects/rasuvaeff/
docker run --rm -v "$PWD":/repo -w /repo/yii3-audit-log-db composer:2 php examples/basic_usage.php
```
