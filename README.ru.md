# rasuvaeff/yii3-audit-log-db

[![Stable Version](https://poser.pugx.org/rasuvaeff/yii3-audit-log-db/v/stable)](https://packagist.org/packages/rasuvaeff/yii3-audit-log-db)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/yii3-audit-log-db/downloads)](https://packagist.org/packages/rasuvaeff/yii3-audit-log-db)
[![Build](https://github.com/rasuvaeff/yii3-audit-log-db/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/yii3-audit-log-db/actions)
[![Static analysis](https://github.com/rasuvaeff/yii3-audit-log-db/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/yii3-audit-log-db/actions)
[![Psalm Level](https://shepherd.dev/github/rasuvaeff/yii3-audit-log-db/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-audit-log-db)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-audit-log-db/php)](https://packagist.org/packages/rasuvaeff/yii3-audit-log-db)
[![License](https://poser.pugx.org/rasuvaeff/yii3-audit-log-db/license)](https://packagist.org/packages/rasuvaeff/yii3-audit-log-db)
[English version](README.md)

DB-backed реализация `AuditWriter` для пакета
[`rasuvaeff/yii3-audit-log`](https://github.com/rasuvaeff/yii3-audit-log).
Персистит audit-события в любую БД, совместимую с `yiisoft/db` (SQLite, MySQL,
PostgreSQL и т.д.).

> Используете AI-ассистента? В [llms.txt](llms.txt) — компактный API-справочник.

## Требования

- PHP 8.3+
- `rasuvaeff/yii3-audit-log` ^1.0
- `yiisoft/db` ^2.0

## Установка

```bash
composer require rasuvaeff/yii3-audit-log rasuvaeff/yii3-audit-log-db
```

## Миграция

Примените поставляемую миграцию, чтобы создать таблицу `audit_log`:

```php
// Register the migration in your migration runner:
// migrations/M260620000000CreateAuditLogTable.php
// from vendor/rasuvaeff/yii3-audit-log-db/migrations/
```

Миграция создаёт:

| Колонка | Тип | Примечания |
|---|---|---|
| `id` | VARCHAR(32) PK | 32-символьный hex из `AuditLogger` |
| `actor_type` | VARCHAR(32) | `user` / `system` |
| `actor_id` | VARCHAR(255) NULL | null для системного actor-а |
| `actor_name` | VARCHAR(255) NULL | отображаемое имя, необязательно |
| `action` | VARCHAR(64) | `create` / `update` / `delete` / кастомное |
| `subject_type` | VARCHAR(255) | тип сущности, например `order` |
| `subject_id` | VARCHAR(255) | идентификатор сущности |
| `changes` | TEXT | JSON-массив объектов `{field, old, new}` |
| `occurred_at` | VARCHAR(30) | `Y-m-d H:i:s` UTC |
| `request_id` | VARCHAR(255) NULL | из `AuditMetadata` |
| `ip` | VARCHAR(45) NULL | из `AuditMetadata` |
| `user_agent` | TEXT NULL | из `AuditMetadata` |

Индексы: `(subject_type, subject_id, occurred_at)`, `(actor_type, actor_id, occurred_at)`, `(occurred_at)`.

## config-plugin Yii3

Установите `rasuvaeff/yii3-audit-log` (ядро) и `rasuvaeff/yii3-audit-log-db` (адаптер).
`config/di.php` адаптера автоматически биндит `AuditWriter` на `DbAuditWriter`.

`config/di.php` ядра конфигурирует `AuditLogger`. В конфиге приложения достаточно
забиндить только `ClockInterface`:

```php
// config/common/di/clock.php
use Psr\Clock\ClockInterface;

return [
    ClockInterface::class => MySystemClock::class,
];
```

Своё имя таблицы через params:

```php
// config/common/params.php
return [
    'rasuvaeff/yii3-audit-log-db' => [
        'table' => 'my_audit_log',
    ],
];
```

## Использование

### Запись через AuditLogger

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

### Прямое использование

```php
use Rasuvaeff\Yii3AuditLogDb\DbAuditWriter;

$writer = new DbAuditWriter(db: $db, table: 'audit_log');
$writer->write(event: $auditEvent);
```

## Безопасность

- `DbAuditWriter` валидирует имя таблицы регуляркой `/^[A-Za-z_]\w*(\.[A-Za-z_]\w*)?$/` — имена с указанием схемы вроде `public.audit_log` разрешены; произвольные строки отбрасываются.
- Все значения полей события передаются как bound parameters через `yiisoft/db` — риска SQL-инъекций нет.
- Значения колонки `changes` — то, что лежит в `AuditChangeSet`. Применяйте `SensitiveValueMasker` в `AuditLogger` до этого writer-а (по умолчанию так и есть в ядре DI).

## Примеры

Запускаемые скрипты — см. [`examples/`](examples/).

## Разработка

```bash
# from monorepo root (/home/rasuvaeff/projects/rasuvaeff)
make -C yii3-audit-log-db install
make -C yii3-audit-log-db build
make -C yii3-audit-log-db cs-fix
make -C yii3-audit-log-db test
```

Или напрямую через Docker:

```bash
docker run --rm -v "$PWD":/repo -w /repo/yii3-audit-log-db composer:2 composer build
```

## Лицензия

BSD-3-Clause. См. [LICENSE.md](LICENSE.md).
