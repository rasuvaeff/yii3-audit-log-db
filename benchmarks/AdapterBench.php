<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AuditLogDb\Benchmarks;

use DateTimeImmutable;
use Rasuvaeff\Yii3AuditLog\AuditActor;
use Rasuvaeff\Yii3AuditLog\AuditChangeSet;
use Rasuvaeff\Yii3AuditLog\AuditEvent;
use Rasuvaeff\Yii3AuditLog\AuditSubject;
use Rasuvaeff\Yii3AuditLogDb\AuditEventSerializer;
use Testo\Bench;

final class AdapterBench
{
    #[Bench(
        callables: [
            'with-changes' => [self::class, 'serializeWithChanges'],
        ],
        calls: 1_000,
        iterations: 10,
    )]
    public static function serializeEmpty(): array
    {
        $event = new AuditEvent(
            id: 'evt-001',
            actor: new AuditActor(type: 'user', id: '42', name: 'Alice'),
            action: 'view',
            subject: new AuditSubject(type: 'order', id: '99'),
            changeSet: AuditChangeSet::empty(),
            occurredAt: new DateTimeImmutable('2024-01-15T10:30:00Z'),
        );

        return AuditEventSerializer::serialize(event: $event);
    }

    public static function serializeWithChanges(): array
    {
        $changeSet = AuditChangeSet::fromArrays(
            old: ['status' => 'pending', 'amount' => 100, 'note' => null, 'tag' => 'new'],
            new: ['status' => 'shipped', 'amount' => 150, 'note' => 'express', 'tag' => 'urgent'],
        );
        $event = new AuditEvent(
            id: 'evt-002',
            actor: new AuditActor(type: 'user', id: '42', name: 'Alice'),
            action: 'update',
            subject: new AuditSubject(type: 'order', id: '99'),
            changeSet: $changeSet,
            occurredAt: new DateTimeImmutable('2024-01-15T10:30:00Z'),
        );

        return AuditEventSerializer::serialize(event: $event);
    }
}
