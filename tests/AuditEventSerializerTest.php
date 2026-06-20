<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AuditLogDb\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3AuditLog\AuditActor;
use Rasuvaeff\Yii3AuditLog\AuditChangeSet;
use Rasuvaeff\Yii3AuditLog\AuditEvent;
use Rasuvaeff\Yii3AuditLog\AuditMetadata;
use Rasuvaeff\Yii3AuditLog\AuditSubject;
use Rasuvaeff\Yii3AuditLogDb\AuditEventSerializer;

#[CoversClass(AuditEventSerializer::class)]
final class AuditEventSerializerTest extends TestCase
{
    #[Test]
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

        $this->assertSame('abc123', $row['id']);
        $this->assertSame('user', $row['actor_type']);
        $this->assertSame('u1', $row['actor_id']);
        $this->assertSame('Alice', $row['actor_name']);
        $this->assertSame('update', $row['action']);
        $this->assertSame('order', $row['subject_type']);
        $this->assertSame('42', $row['subject_id']);
        $this->assertSame('2026-06-20 10:00:00', $row['occurred_at']);
        $this->assertSame('req-1', $row['request_id']);
        $this->assertSame('127.0.0.1', $row['ip']);
        $this->assertSame('Test/1.0', $row['user_agent']);
    }

    #[Test]
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

        $this->assertCount(2, $changes);
        $fields = array_column($changes, 'field');
        $this->assertContains('name', $fields);
        $this->assertContains('age', $fields);
    }

    #[Test]
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

        $this->assertSame('[]', $row['changes']);
    }

    #[Test]
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

        $this->assertSame('system', $row['actor_type']);
        $this->assertNull($row['actor_id']);
        $this->assertNull($row['actor_name']);
    }

    #[Test]
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

        $this->assertNull($row['request_id']);
        $this->assertNull($row['ip']);
        $this->assertNull($row['user_agent']);
    }

    #[Test]
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

        $this->assertSame('2026-06-20 10:00:00', $row['occurred_at']);
    }

    #[Test]
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

        $this->assertSame('price', $changes[0]['field']);
        $this->assertNull($changes[0]['old']);
        $this->assertSame(9.99, $changes[0]['new']);
    }
}
