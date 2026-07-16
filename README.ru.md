# rasuvaeff/yii3-audit-log-db
[![Stable Version](https://poser.pugx.org/rasuvaeff/yii3-audit-log-db/v/stable)](https://packagist.org/packages/rasuvaeff/yii3-audit-log-db)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/yii3-audit-log-db/downloads)](https://packagist.org/packages/rasuvaeff/yii3-audit-log-db)
[![Build](https://github.com/rasuvaeff/yii3-audit-log-db/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/yii3-audit-log-db/actions)
[![Static analysis](https://github.com/rasuvaeff/yii3-audit-log-db/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/yii3-audit-log-db/actions)
[![Psalm Level](https://shepherd.dev/github/rasuvaeff/yii3-audit-log-db/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-audit-log-db)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-audit-log-db/php)](https://packagist.org/packages/rasuvaeff/yii3-audit-log-db)
[![License](https://poser.pugx.org/rasuvaeff/yii3-audit-log-db/license)](https://packagist.org/packages/rasuvaeff/yii3-audit-log-db)
Database-backed `AuditWriter` for [`rasuvaeff/yii3-audit-log`](https://github.com/rasuvaeff/yii3-audit-log).
Сохраняет события аудита в любой базе данных, совместимой с yiisoft/db (SQLite, MySQL, PostgreSQL и т. д.).

 > Используете помощника по программированию с искусственным интеллектом? [llms.txt](llms.txt) содержит компактную ссылку на API, которую вы можете использовать. @@ЛИНИЯ@@
## Требования
- PHP 8.3+
 - `rasuvaeff/yii3-audit-log` ^1.0
 - `yiisoft/db` ^2.0

## Установка
```bash
composer require rasuvaeff/yii3-audit-log rasuvaeff/yii3-audit-log-db
```
## Миграция
Запустите комплексную миграцию, чтобы создать таблицу Audit_log:
.
```php
// Register the migration in your migration runner:
// migrations/M260620000000CreateAuditLogTable.php
// from vendor/rasuvaeff/yii3-audit-log-db/migrations/
```
В результате миграции создается:

 | Столбец | Тип | Заметки |
 |---|---|---|
 | `идентификатор` | ВАРЧАР(32) ПК | 32-значный шестнадцатеричный код из `AuditLogger` |
 | `actor_type` | ВАРЧАР(32) | `пользователь` / `система` |
 | `actor_id` | ВАРЧАР(255) НОЛЬ | null для системного актера |
 | `имя_актёра` | ВАРЧАР(255) НОЛЬ | отображаемое имя, необязательно |
 | `действие` | ВАРЧАР(64) | `создать`/`обновить`/`удалить`/настроить |
 | `subject_type` | ВАРЧАР(255) | тип объекта, например. `заказ` |
 | `subject_id` | ВАРЧАР(255) | идентификатор объекта |
 | `изменения` | ТЕКСТ | JSON-массив объектов `{field, old, new}` |
 | `произошло_at` | ВАРЧАР(30) | `Г-м-д Ч:и:с` UTC |
 | `request_id` | ВАРЧАР(255) НОЛЬ | из `AuditMetadata` |
 | `IP` | ВАРЧАР(45) НОЛЬ | из `AuditMetadata` |
 | `user_agent` | ТЕКСТ NULL | из `AuditMetadata` |

 Индексы: `(тип_субъекта, идентификатор_субъекта, произошло_в)`, `(тип_актера, идентификатор_актера, произошло_в)`, `(произошло_в)`. @@ЛИНИЯ@@
## Конфигурационный плагин Yii3
Установите rasuvaeff/yii3-audit-log (ядро) и rasuvaeff/yii3-audit-log-db (адаптер).
 `config/di.php` адаптера автоматически связывает `AuditWriter` с `DbAuditWriter`.

 `config/di.php` ядра подключает `AuditLogger`. Вам нужно только связать `ClockInterface`
 в конфигурации вашего приложения:

```php
// config/common/di/clock.php
use Psr\Clock\ClockInterface;

return [
    ClockInterface::class => MySystemClock::class,
];
```
Пользовательское имя таблицы через параметры:

```php
// config/common/params.php
return [
    'rasuvaeff/yii3-audit-log-db' => [
        'table' => 'my_audit_log',
    ],
];
```
## Использование
### Пишите через AuditLogger
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
### Используйте напрямую
```php
use Rasuvaeff\Yii3AuditLogDb\DbAuditWriter;

$writer = new DbAuditWriter(db: $db, table: 'audit_log');
$writer->write(event: $auditEvent);
```
## Безопасность
- `DbAuditWriter` проверяет имя таблицы на соответствие `/^[A-Za-z_]\w*(\.[A-Za-z_]\w*)?$/` — разрешены имена с указанием схемы, такие как `public.audit_log`; произвольные строки отклоняются.
 - Все значения полей событий передаются как связанные параметры через `yiisoft/db` — риск внедрения SQL отсутствует.
 — значения `changes` — это всё, что содержит `AuditChangeSet`. Примените SensitiveValueMasker в AuditLogger перед запуском этого средства записи (по умолчанию в ядре DI). @@ЛИНИЯ@@
## Примеры
См. [`examples/`](examples/) для ознакомления с работоспособными скриптами. @@ЛИНИЯ@@
## Разработка
```bash
# from monorepo root (/home/rasuvaeff/projects/rasuvaeff)
make -C yii3-audit-log-db install
make -C yii3-audit-log-db build
make -C yii3-audit-log-db cs-fix
make -C yii3-audit-log-db test
```
Или напрямую с помощью Docker:

```bash
docker run --rm -v "$PWD":/repo -w /repo/yii3-audit-log-db composer:2 composer build
```
## Лицензия
BSD-3-пункт. См. [LICENSE.md](LICENSE.md).
