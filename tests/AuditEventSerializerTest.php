<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AuditLogDb\Tests;

use DateTimeImmutable;
use Rasuvaeff\Yii3AuditLog\AuditActor;
use Rasuvaeff\Yii3AuditLog\AuditChangeSet;
use Rasuvaeff\Yii3AuditLog\AuditEvent;
use Rasuvaeff\Yii3AuditLog\AuditMetadata;
use Rasuvaeff\Yii3AuditLog\AuditSubject;
use Rasuvaeff\Yii3AuditLogDb\AuditEventSerializer;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(AuditEventSerializer::class)]
final class AuditEventSerializerTest
{
    public function serializesAllScalarFields(): void
    {
        $event = new AuditEvent(
            id: 'abc123',
            actor: AuditActor::user(id: 'u1', name: 'Alice'),
            action: 'update',
            subject: AuditSubject::of(type: 'order', id: '42'),
            changeSet: AuditChangeSet::empty(),
            occurredAt: new DateTimeImmutable('2026-06-20 10:00:00 UTC'),
            metadata: new AuditMetadata(requestId: 'req-1', ip: '127.0.0.1', userAgent: 'Test/1.0'),
        );

        $row = AuditEventSerializer::serialize(event: $event);

        Assert::same($row['id'], 'abc123');
        Assert::same($row['actor_type'], 'user');
        Assert::same($row['actor_id'], 'u1');
        Assert::same($row['actor_name'], 'Alice');
        Assert::same($row['action'], 'update');
        Assert::same($row['subject_type'], 'order');
        Assert::same($row['subject_id'], '42');
        Assert::same($row['occurred_at'], '2026-06-20 10:00:00');
        Assert::same($row['request_id'], 'req-1');
        Assert::same($row['ip'], '127.0.0.1');
        Assert::same($row['user_agent'], 'Test/1.0');
    }

    public function serializesChangesAsJsonArray(): void
    {
        $event = new AuditEvent(
            id: 'e1',
            actor: AuditActor::system(),
            action: 'update',
            subject: AuditSubject::of(type: 'user', id: '1'),
            changeSet: AuditChangeSet::fromArrays(
                old: ['name' => 'Alice', 'age' => 20],
                new: ['name' => 'Bob', 'age' => 21],
            ),
            occurredAt: new DateTimeImmutable('2026-06-20 10:00:00 UTC'),
        );

        $row = AuditEventSerializer::serialize(event: $event);
        /** @var list<array{field: string, old: mixed, new: mixed}> $changes */
        $changes = json_decode((string) $row['changes'], associative: true, flags: JSON_THROW_ON_ERROR);

        Assert::count($changes, 2);
        $fields = array_column($changes, 'field');
        Assert::true(in_array('name', $fields, true));
        Assert::true(in_array('age', $fields, true));
    }

    public function serializesEmptyChangeSetAsEmptyJsonArray(): void
    {
        $event = new AuditEvent(
            id: 'e1',
            actor: AuditActor::system(),
            action: 'create',
            subject: AuditSubject::of(type: 'config', id: 'app'),
            changeSet: AuditChangeSet::empty(),
            occurredAt: new DateTimeImmutable('2026-06-20 10:00:00 UTC'),
        );

        $row = AuditEventSerializer::serialize(event: $event);

        Assert::same($row['changes'], '[]');
    }

    public function serializesSystemActorWithNullIds(): void
    {
        $event = new AuditEvent(
            id: 'e1',
            actor: AuditActor::system(),
            action: 'delete',
            subject: AuditSubject::of(type: 'item', id: '7'),
            changeSet: AuditChangeSet::empty(),
            occurredAt: new DateTimeImmutable('2026-06-20 10:00:00 UTC'),
        );

        $row = AuditEventSerializer::serialize(event: $event);

        Assert::same($row['actor_type'], 'system');
        Assert::null($row['actor_id']);
        Assert::null($row['actor_name']);
    }

    public function serializesNullMetadataAsNullColumns(): void
    {
        $event = new AuditEvent(
            id: 'e1',
            actor: AuditActor::system(),
            action: 'delete',
            subject: AuditSubject::of(type: 'item', id: '1'),
            changeSet: AuditChangeSet::empty(),
            occurredAt: new DateTimeImmutable('2026-06-20 10:00:00 UTC'),
        );

        $row = AuditEventSerializer::serialize(event: $event);

        Assert::null($row['request_id']);
        Assert::null($row['ip']);
        Assert::null($row['user_agent']);
    }

    public function normalizesOccurredAtToUtc(): void
    {
        $event = new AuditEvent(
            id: 'e1',
            actor: AuditActor::system(),
            action: 'create',
            subject: AuditSubject::of(type: 'x', id: '1'),
            changeSet: AuditChangeSet::empty(),
            occurredAt: new DateTimeImmutable('2026-06-20 13:00:00+03:00'),
        );

        $row = AuditEventSerializer::serialize(event: $event);

        Assert::same($row['occurred_at'], '2026-06-20 10:00:00');
    }

    public function serializesChangeFieldValues(): void
    {
        $event = new AuditEvent(
            id: 'e1',
            actor: AuditActor::system(),
            action: 'update',
            subject: AuditSubject::of(type: 'product', id: '5'),
            changeSet: AuditChangeSet::fromArrays(
                old: ['price' => null],
                new: ['price' => 9.99],
            ),
            occurredAt: new DateTimeImmutable('2026-06-20 10:00:00 UTC'),
        );

        $row = AuditEventSerializer::serialize(event: $event);
        /** @var list<array{field: string, old: mixed, new: mixed}> $changes */
        $changes = json_decode((string) $row['changes'], associative: true, flags: JSON_THROW_ON_ERROR);

        Assert::same($changes[0]['field'], 'price');
        Assert::null($changes[0]['old']);
        Assert::same($changes[0]['new'], 9.99);
    }
}
