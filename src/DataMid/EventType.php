<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\DataMid;

/**
 * Stable event codes accepted by data-mid.
 */
final class EventType
{
    public const USER_REGISTERED = 'user.registered';
    public const USER_ACTIVE = 'user.active';
    public const USER_PROFILE_UPDATED = 'user.profile.updated';
    public const USER_PROFILE_COMPLETED = 'user.profile.completed';
    public const USER_WHATSAPP_UPDATED = 'user.whatsapp.updated';
    public const USER_DEREGISTERED = 'user.deregistered';
    public const USER_LIFECYCLE_CHANGED = 'user.lifecycle.changed';
    public const USER_OVERDUE_DAILY = 'user.overdue.daily';

    public const IDENTITY_DEVICE_OBSERVED = 'identity.device.observed';

    public const LEAD_REGISTRATION_STARTED = 'lead.registration.started';

    public const LOAN_APPLICATION_SUBMITTED = 'loan.application.submitted';
    public const LOAN_APPLICATION_STATUS_CHANGED = 'loan.application.status_changed';
    public const LOAN_REPAYMENT_SCHEDULED = 'loan.repayment.scheduled';

    /** @return array<int, string> */
    public static function all(): array
    {
        return [
            self::USER_REGISTERED,
            self::USER_ACTIVE,
            self::USER_PROFILE_UPDATED,
            self::USER_PROFILE_COMPLETED,
            self::USER_WHATSAPP_UPDATED,
            self::USER_DEREGISTERED,
            self::USER_LIFECYCLE_CHANGED,
            self::USER_OVERDUE_DAILY,
            self::IDENTITY_DEVICE_OBSERVED,
            self::LEAD_REGISTRATION_STARTED,
            self::LOAN_APPLICATION_SUBMITTED,
            self::LOAN_APPLICATION_STATUS_CHANGED,
            self::LOAN_REPAYMENT_SCHEDULED,
        ];
    }

    public static function isKnown(string $eventType): bool
    {
        return in_array($eventType, self::all(), true);
    }
}
