<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Rasuvaeff\Yii3AuditLog\AuditActor;
use Rasuvaeff\Yii3AuditLog\AuditChangeSet;
use Rasuvaeff\Yii3AuditLog\AuditLogger;
use Rasuvaeff\Yii3AuditLog\AuditMetadata;
use Rasuvaeff\Yii3AuditLog\AuditSubject;
use Rasuvaeff\Yii3AuditLogDb\DbAuditWriter;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;
use Yiisoft\Test\Support\Clock\StaticClock;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

$driver = new SqliteDriver(dsn: 'sqlite::memory:');
$schemaCache = new SchemaCache(psrCache: new MemorySimpleCache());
$db = new SqliteConnection(driver: $driver, schemaCache: $schemaCache);
$db->open();

$db->createCommand(sql: 'CREATE TABLE audit_log (id VARCHAR(32) PRIMARY KEY, actor_type VARCHAR(32) NOT NULL, actor_id VARCHAR(255), actor_name VARCHAR(255), action VARCHAR(64) NOT NULL, subject_type VARCHAR(255) NOT NULL, subject_id VARCHAR(255) NOT NULL, changes TEXT NOT NULL, occurred_at VARCHAR(30) NOT NULL, request_id VARCHAR(255), ip VARCHAR(45), user_agent TEXT)')->execute();

$writer = new DbAuditWriter(db: $db);
$clock = new StaticClock(new DateTimeImmutable('2026-06-20 10:00:00'));
$logger = new AuditLogger(writer: $writer, clock: $clock);

$logger->logCreate(
    actor: AuditActor::user(id: '1', name: 'Alice'),
    subject: AuditSubject::of(type: 'order', id: '100'),
    changes: AuditChangeSet::fromArrays(
        old: [],
        new: ['status' => 'new', 'total' => 0],
    ),
);

$logger->logChange(
    actor: AuditActor::user(id: '1', name: 'Alice'),
    subject: AuditSubject::of(type: 'order', id: '100'),
    changes: AuditChangeSet::fromArrays(
        old: ['status' => 'new', 'total' => 0],
        new: ['status' => 'paid', 'total' => 99.95],
    ),
    metadata: new AuditMetadata(requestId: 'req-abc', ip: '127.0.0.1'),
);

$logger->logDelete(
    actor: AuditActor::system(),
    subject: AuditSubject::of(type: 'order', id: '99'),
    changes: AuditChangeSet::empty(),
);

$rows = (new Query($db))->from('audit_log')->orderBy('occurred_at')->all();

foreach ($rows as $row) {
    echo sprintf(
        "[%s] %s %s %s/%s\n",
        $row['occurred_at'],
        $row['actor_type'] . ($row['actor_name'] !== null ? '(' . $row['actor_name'] . ')' : ''),
        $row['action'],
        $row['subject_type'],
        $row['subject_id'],
    );
}

echo 'Total: ' . count($rows) . " events\n";

$db->close();
