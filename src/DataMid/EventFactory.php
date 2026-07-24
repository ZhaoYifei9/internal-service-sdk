<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\DataMid;

/**
 * Builds the stable data-mid event envelope and documented event payloads.
 */
final class EventFactory
{
    private string $countryCode;

    /** @var callable */
    private $clock;

    /** @var callable */
    private $nonceFactory;

    public function __construct(
        string $countryCode,
        ?callable $clock = null,
        ?callable $nonceFactory = null
    ) {
        $this->countryCode = strtoupper(trim($countryCode));
        $this->clock = $clock ?? static function (): int {
            return time();
        };
        $this->nonceFactory = $nonceFactory ?? static function (): string {
            return bin2hex(random_bytes(16));
        };
    }

    public function countryCode(): string
    {
        return $this->countryCode;
    }

    /** @param array<string, mixed> $data */
    public function custom(
        string $eventType,
        string $userId,
        string $appId,
        string $phone,
        array $data = [],
        ?string $eventId = null,
        ?string $dedupKey = null,
        ?int $occurredAt = null
    ): PreparedEvent {
        $occurredAt = $occurredAt ?? $this->now();
        if ($eventId === null || $eventId === '') {
            $eventId = EventId::business(
                $this->countryCode,
                $appId,
                $userId,
                $eventType,
                [$occurredAt, (string) ($this->nonceFactory)()]
            );
        }

        return new PreparedEvent([
            'event_type' => $eventType,
            'country_code' => $this->countryCode,
            'app_id' => $appId,
            'user_id' => $userId,
            'phone' => $phone,
            'event_id' => $eventId,
            'timestamp' => $occurredAt,
            'data' => $data,
        ], $dedupKey ?? $eventId);
    }

    /** @param array<string, mixed> $data */
    public function userRegistered(string $userId, string $appId, array $data): PreparedEvent
    {
        $eventId = EventId::business(
            $this->countryCode,
            $appId,
            $userId,
            EventType::USER_REGISTERED
        );

        return $this->custom(
            EventType::USER_REGISTERED,
            $userId,
            $appId,
            (string) ($data['phone'] ?? ''),
            $data,
            $eventId
        );
    }

    /** @param array<string, mixed> $data */
    public function registrationStarted(string $appId, string $phone, array $data = []): PreparedEvent
    {
        $occurredAt = $this->now();
        $eventId = EventId::business(
            $this->countryCode,
            $appId,
            '',
            EventType::LEAD_REGISTRATION_STARTED,
            [hash('sha256', $phone), date('Y-m-d', $occurredAt)]
        );

        return $this->custom(
            EventType::LEAD_REGISTRATION_STARTED,
            '',
            $appId,
            $phone,
            array_merge($data, [
                'started_at' => $occurredAt,
                'source' => 'OTP_REGISTER',
            ]),
            $eventId,
            null,
            $occurredAt
        );
    }

    public function lifecycleChanged(
        string $userId,
        string $appId,
        string $phone,
        string $stage,
        ?string $orderId = null,
        ?string $settlementType = null,
        int $changedAt = 0
    ): PreparedEvent {
        $changedAt = $changedAt ?: $this->now();
        $normalizedStage = strtoupper($stage);
        $data = [
            'lifecycle_stage' => $stage,
            'changed_at' => $changedAt,
        ];
        if ($orderId) {
            $data['order_no'] = $orderId;
        }
        if ($stage === 'SETTLED' && $settlementType !== null) {
            $data['settlement_type'] = strtoupper($settlementType);
        }

        $eventId = $orderId === null
            ? null
            : EventId::business(
                $this->countryCode,
                $appId,
                $userId,
                EventType::USER_LIFECYCLE_CHANGED,
                [$orderId, $normalizedStage, $changedAt]
            );

        return $this->custom(
            EventType::USER_LIFECYCLE_CHANGED,
            $userId,
            $appId,
            $phone,
            $data,
            $eventId,
            null,
            $changedAt
        );
    }

    public function overdueDaily(
        string $userId,
        string $appId,
        string $phone,
        string $orderNo,
        int $currentDays,
        int $maxDays = 0,
        string $batchDate = '',
        ?int $occurredAt = null
    ): PreparedEvent {
        $occurredAt = $occurredAt ?? $this->now();
        $batchDate = $batchDate !== '' ? $batchDate : date('Y-m-d', $occurredAt);
        $eventId = EventId::business(
            $this->countryCode,
            $appId,
            $userId,
            EventType::USER_OVERDUE_DAILY,
            [$orderNo, $batchDate]
        );

        return $this->custom(
            EventType::USER_OVERDUE_DAILY,
            $userId,
            $appId,
            $phone,
            [
                'order_no' => $orderNo,
                'current_overdue_days' => $currentDays,
                'max_overdue_days' => $maxDays,
                'batch_date' => $batchDate,
            ],
            $eventId,
            null,
            $occurredAt
        );
    }

    public function userActive(
        string $userId,
        string $appId,
        string $phone,
        int $interval
    ): PreparedEvent {
        $activeAt = $this->now();
        $eventId = EventId::active(
            $this->countryCode,
            $appId,
            $userId,
            $activeAt,
            $interval
        );

        return $this->custom(
            EventType::USER_ACTIVE,
            $userId,
            $appId,
            $phone,
            ['active_at' => $activeAt],
            $eventId,
            EventId::activeDedupKey($this->countryCode, $appId, $userId),
            $activeAt
        );
    }

    public function identityDeviceObserved(
        string $userId,
        string $appId,
        string $phone,
        string $installUuid,
        string $platform,
        string $gaid = '',
        string $installSource = ''
    ): PreparedEvent {
        $observedAt = $this->now();
        $platform = strtoupper($platform);
        $eventId = EventId::business(
            $this->countryCode,
            $appId,
            $userId,
            EventType::IDENTITY_DEVICE_OBSERVED,
            [
                $installUuid,
                $platform,
                $gaid,
                $installSource,
                (int) floor($observedAt / 60),
            ]
        );

        return $this->custom(
            EventType::IDENTITY_DEVICE_OBSERVED,
            $userId,
            $appId,
            $phone,
            [
                'install_uuid' => $installUuid,
                'platform' => $platform,
                'gaid' => $gaid,
                'install_source' => $installSource,
                'observed_at' => $observedAt,
            ],
            $eventId,
            null,
            $observedAt
        );
    }

    /** @param array<string, mixed> $profileData */
    public function profileUpdated(
        string $userId,
        string $appId,
        string $phone,
        array $profileData,
        int $windowSeconds
    ): PreparedEvent {
        $reportedAt = $this->now();
        $eventId = EventId::snapshot(
            $this->countryCode,
            $appId,
            $userId,
            EventType::USER_PROFILE_UPDATED,
            $profileData,
            $reportedAt,
            $windowSeconds
        );

        return $this->custom(
            EventType::USER_PROFILE_UPDATED,
            $userId,
            $appId,
            $phone,
            $profileData,
            $eventId,
            null,
            $reportedAt
        );
    }

    public function profileCompleted(string $userId, string $appId, string $phone): PreparedEvent
    {
        $completedAt = $this->now();
        $eventId = EventId::business(
            $this->countryCode,
            $appId,
            $userId,
            EventType::USER_PROFILE_COMPLETED
        );

        return $this->custom(
            EventType::USER_PROFILE_COMPLETED,
            $userId,
            $appId,
            $phone,
            ['completed_at' => $completedAt],
            $eventId,
            null,
            $completedAt
        );
    }

    public function applicationSubmitted(
        string $userId,
        string $appId,
        string $phone,
        string $orderNo,
        string $productId = '',
        string $productName = ''
    ): PreparedEvent {
        $submittedAt = $this->now();
        $eventId = EventId::business(
            $this->countryCode,
            $appId,
            $userId,
            EventType::LOAN_APPLICATION_SUBMITTED,
            [$orderNo]
        );

        return $this->custom(
            EventType::LOAN_APPLICATION_SUBMITTED,
            $userId,
            $appId,
            $phone,
            [
                'order_no' => $orderNo,
                'product_id' => $productId,
                'product_name' => $productName,
                'submitted_at' => $submittedAt,
            ],
            $eventId,
            null,
            $submittedAt
        );
    }

    /** @param array<string, mixed> $orderContext */
    public function applicationStatusChanged(
        string $userId,
        string $appId,
        string $phone,
        string $orderNo,
        string $status,
        int $changedAt = 0,
        array $orderContext = []
    ): PreparedEvent {
        $changedAt = $changedAt ?: $this->now();
        $status = strtoupper($status);
        $eventId = EventId::business(
            $this->countryCode,
            $appId,
            $userId,
            EventType::LOAN_APPLICATION_STATUS_CHANGED,
            [$orderNo, $status, $changedAt]
        );

        return $this->custom(
            EventType::LOAN_APPLICATION_STATUS_CHANGED,
            $userId,
            $appId,
            $phone,
            array_merge([
                'order_no' => $orderNo,
                'status' => $status,
                'changed_at' => $changedAt,
            ], OrderContext::normalize($orderContext)),
            $eventId,
            null,
            $changedAt
        );
    }

    /** @param array<string, mixed> $orderContext */
    public function repaymentScheduled(
        string $userId,
        string $appId,
        string $phone,
        string $orderNo,
        int $dueAt,
        float $amount = 0,
        array $orderContext = []
    ): PreparedEvent {
        $scheduledAt = $this->now();
        $eventId = EventId::business(
            $this->countryCode,
            $appId,
            $userId,
            EventType::LOAN_REPAYMENT_SCHEDULED,
            [$orderNo, $dueAt, number_format($amount, 2, '.', '')]
        );

        return $this->custom(
            EventType::LOAN_REPAYMENT_SCHEDULED,
            $userId,
            $appId,
            $phone,
            array_merge([
                'order_no' => $orderNo,
                'due_at' => $dueAt,
                'amount' => $amount,
                'scheduled_at' => $scheduledAt,
            ], OrderContext::normalize($orderContext)),
            $eventId,
            null,
            $scheduledAt
        );
    }

    public function whatsappUpdated(
        string $userId,
        string $appId,
        string $phone,
        string $whatsappStatus,
        string $whatsappNumber,
        int $windowSeconds
    ): PreparedEvent {
        $reportedAt = $this->now();
        $data = [
            'whatsapp_status' => $whatsappStatus,
            'whatsapp_number' => $whatsappNumber,
        ];
        $eventId = EventId::snapshot(
            $this->countryCode,
            $appId,
            $userId,
            EventType::USER_WHATSAPP_UPDATED,
            $data,
            $reportedAt,
            $windowSeconds
        );

        return $this->custom(
            EventType::USER_WHATSAPP_UPDATED,
            $userId,
            $appId,
            $phone,
            $data,
            $eventId,
            null,
            $reportedAt
        );
    }

    public function deregistered(
        string $userId,
        string $appId,
        string $phone,
        int $reasonType = 0,
        string $reasonText = ''
    ): PreparedEvent {
        $deregisteredAt = $this->now();
        $eventId = EventId::business(
            $this->countryCode,
            $appId,
            $userId,
            EventType::USER_DEREGISTERED
        );

        return $this->custom(
            EventType::USER_DEREGISTERED,
            $userId,
            $appId,
            $phone,
            [
                'deregistered_at' => $deregisteredAt,
                'reason_type' => $reasonType,
                'reason_text' => $reasonText,
            ],
            $eventId,
            null,
            $deregisteredAt
        );
    }

    private function now(): int
    {
        return (int) ($this->clock)();
    }
}
