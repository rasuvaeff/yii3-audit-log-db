<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AuditLogDb;

use DateTimeZone;
use Rasuvaeff\Yii3AuditLog\AuditChange;
use Rasuvaeff\Yii3AuditLog\AuditEvent;

/**
 * @internal
 */
final class AuditEventSerializer
{
    private const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    private static ?DateTimeZone $utc = null;

    /**
     * @return array<string, int|string|null>
     */
    public static function serialize(AuditEvent $event): array
    {
        $metadata = $event->getMetadata();

        return [
            'id' => $event->getId(),
            'actor_type' => $event->getActor()->getType(),
            'actor_id' => $event->getActor()->getId(),
            'actor_name' => $event->getActor()->getName(),
            'action' => $event->getAction(),
            'subject_type' => $event->getSubject()->getType(),
            'subject_id' => $event->getSubject()->getId(),
            'changes' => self::serializeChanges(event: $event),
            'occurred_at' => $event->getOccurredAt()
                ->setTimezone(self::utc())
                ->format(self::DATETIME_FORMAT),
            'request_id' => $metadata?->getRequestId(),
            'ip' => $metadata?->getIp(),
            'user_agent' => $metadata?->getUserAgent(),
        ];
    }

    private static function serializeChanges(AuditEvent $event): string
    {
        $changes = array_map(
            static fn(AuditChange $c): array => [
                'field' => $c->getField(),
                'old' => $c->getOldValue(),
                'new' => $c->getNewValue(),
            ],
            $event->getChangeSet()->getChanges(),
        );

        return json_encode(value: $changes, flags: JSON_THROW_ON_ERROR);
    }

    private static function utc(): DateTimeZone
    {
        return self::$utc ??= new DateTimeZone('UTC');
    }
}
