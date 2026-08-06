<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\DataMid;

use InvalidArgumentException;
use Throwable;

/**
 * High-level producer facade for documented data-mid events.
 *
 * Applications provide only runtime adapters (dispatching, dedup storage and
 * logging); event construction and delivery lifecycle stay consistent across
 * countries.
 */
class DataMidReporter
{
    private DataMidClient $client;

    private EventFactory $eventFactory;

    private DeduplicatorInterface $deduplicator;

    private DispatcherInterface $dispatcher;

    private int $dedupTtl;

    private int $stateSuppressTtl;

    private int $activeInterval;

    private int $batchTimeout;

    /** @var null|callable */
    private $logger;

    /**
     * @param array{dedup_ttl?:int,state_suppress_ttl?:int,active_interval?:int,batch_timeout?:int} $options
     */
    public function __construct(
        DataMidClient $client,
        EventFactory $eventFactory,
        ?DeduplicatorInterface $deduplicator = null,
        ?DispatcherInterface $dispatcher = null,
        array $options = [],
        ?callable $logger = null
    ) {
        $this->client = $client;
        $this->eventFactory = $eventFactory;
        $this->deduplicator = $deduplicator ?? new NullDeduplicator();
        $this->dispatcher = $dispatcher ?? new InlineDispatcher();
        $this->dedupTtl = max(60, (int) ($options['dedup_ttl'] ?? 86400));
        $this->stateSuppressTtl = max(60, (int) ($options['state_suppress_ttl'] ?? 3600));
        $this->activeInterval = max(60, (int) ($options['active_interval'] ?? 600));
        $this->batchTimeout = max(1, min(120, (int) ($options['batch_timeout'] ?? 10)));
        $this->logger = $logger;
    }

    public function isEnabled(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function report(
        string $eventType,
        string $userId,
        string $appId,
        string $phone,
        array $data,
        ?string $eventId = null,
        int $producerDedupTtl = 0,
        ?string $producerDedupKey = null
    ): void {
        $this->dispatchPreparedEvent(
            $this->eventFactory->custom(
                $eventType,
                $userId,
                $appId,
                $phone,
                $data,
                $eventId,
                $producerDedupKey
            ),
            $producerDedupTtl
        );
    }

    /** @param array<int, array<string, mixed>> $events */
    public function reportBatch(array $events): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        foreach ($events as $index => $event) {
            if (!is_array($event) || empty($event['phone'])) {
                throw new InvalidArgumentException("Missing phone for batch event at index {$index}");
            }
        }

        $this->dispatcher->dispatch(function () use ($events): void {
            try {
                $this->client->reportBatch($events);
            } catch (Throwable $exception) {
                $this->log('data-mid-batch', 'Batch report failed', 'error', [
                    'error' => $exception->getMessage(),
                    'count' => count($events),
                ]);
            }
        });
    }

    /**
     * @param array<int, array<string, int|string>> $orders
     */
    public function overdueDailyBatchSync(array $orders, string $batchDate = ''): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        if ($orders === []) {
            return true;
        }

        $batchDate = $batchDate !== '' ? $batchDate : date('Y-m-d');
        $timestamp = time();
        $events = [];
        foreach ($orders as $order) {
            $currentDays = (int) ($order['current_overdue_days'] ?? 0);
            $events[] = $this->eventFactory->overdueDaily(
                (string) ($order['user_id'] ?? ''),
                (string) ($order['app_id'] ?? ''),
                (string) ($order['phone'] ?? ''),
                (string) ($order['order_no'] ?? ''),
                $currentDays,
                (int) ($order['max_overdue_days'] ?? $currentDays),
                $batchDate,
                $timestamp
            )->payload();
        }

        return $this->reportBatchSynchronously($events, EventType::USER_OVERDUE_DAILY);
    }

    /** @param array<string, mixed> $data */
    public function userRegistered(string $userId, string $appId, array $data): void
    {
        $this->dispatchPreparedEvent(
            $this->eventFactory->userRegistered($userId, $appId, $data),
            $this->dedupTtl
        );
    }

    /** @param array<string, mixed> $data */
    public function userAssetSynced(
        string $userId,
        string $appId,
        string $phone,
        array $data = []
    ): void {
        $this->dispatchPreparedEvent(
            $this->eventFactory->userAssetSynced($userId, $appId, $phone, $data),
            60
        );
    }

    /** @param array<string, mixed> $data */
    public function registrationStarted(string $appId, string $phone, array $data = []): void
    {
        $this->dispatchPreparedEvent(
            $this->eventFactory->registrationStarted($appId, $phone, $data),
            $this->dedupTtl
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
    ): void {
        $this->dispatchPreparedEvent(
            $this->eventFactory->lifecycleChanged(
                $userId,
                $appId,
                $phone,
                $stage,
                $orderId,
                $settlementType,
                $changedAt
            ),
            $orderId === null ? 0 : $this->dedupTtl
        );
    }

    public function overdueDaily(
        string $userId,
        string $appId,
        string $phone,
        string $orderNo,
        int $currentDays,
        int $maxDays = 0
    ): void {
        $this->dispatchPreparedEvent(
            $this->eventFactory->overdueDaily(
                $userId,
                $appId,
                $phone,
                $orderNo,
                $currentDays,
                $maxDays
            ),
            $this->dedupTtl
        );
    }

    public function userActive(string $userId, string $appId, string $phone): void
    {
        $this->dispatchPreparedEvent(
            $this->eventFactory->userActive($userId, $appId, $phone, $this->activeInterval),
            $this->activeInterval
        );
    }

    public function isActiveReportSuppressed(string $userId, string $appId): bool
    {
        return $this->deduplicator->isSuppressed(
            EventId::activeDedupKey($this->eventFactory->countryCode(), $appId, $userId)
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
    ): void {
        $this->dispatchPreparedEvent(
            $this->eventFactory->identityDeviceObserved(
                $userId,
                $appId,
                $phone,
                $installUuid,
                $platform,
                $gaid,
                $installSource
            ),
            60
        );
    }

    /** @param array<string, mixed> $profileData */
    public function profileUpdated(string $userId, string $appId, string $phone, array $profileData): void
    {
        $this->dispatchPreparedEvent(
            $this->eventFactory->profileUpdated(
                $userId,
                $appId,
                $phone,
                $profileData,
                $this->stateSuppressTtl
            ),
            $this->stateSuppressTtl
        );
    }

    public function profileCompleted(string $userId, string $appId, string $phone): void
    {
        $this->dispatchPreparedEvent(
            $this->eventFactory->profileCompleted($userId, $appId, $phone),
            $this->dedupTtl
        );
    }

    public function applicationSubmitted(
        string $userId,
        string $appId,
        string $phone,
        string $orderNo,
        string $productId = '',
        string $productName = ''
    ): void {
        $this->dispatchPreparedEvent(
            $this->eventFactory->applicationSubmitted(
                $userId,
                $appId,
                $phone,
                $orderNo,
                $productId,
                $productName
            ),
            $this->dedupTtl
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
    ): void {
        $this->dispatchPreparedEvent(
            $this->eventFactory->applicationStatusChanged(
                $userId,
                $appId,
                $phone,
                $orderNo,
                $status,
                $changedAt,
                $orderContext
            ),
            $this->dedupTtl
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
    ): void {
        $this->dispatchPreparedEvent(
            $this->eventFactory->repaymentScheduled(
                $userId,
                $appId,
                $phone,
                $orderNo,
                $dueAt,
                $amount,
                $orderContext
            ),
            $this->dedupTtl
        );
    }

    public function whatsappUpdated(
        string $userId,
        string $appId,
        string $phone,
        string $whatsappStatus,
        string $whatsappNumber
    ): void {
        $this->dispatchPreparedEvent(
            $this->eventFactory->whatsappUpdated(
                $userId,
                $appId,
                $phone,
                $whatsappStatus,
                $whatsappNumber,
                $this->stateSuppressTtl
            ),
            $this->stateSuppressTtl
        );
    }

    public function deregistered(
        string $userId,
        string $appId,
        string $phone,
        int $reasonType = 0,
        string $reasonText = ''
    ): void {
        $this->dispatchPreparedEvent(
            $this->eventFactory->deregistered(
                $userId,
                $appId,
                $phone,
                $reasonType,
                $reasonText
            ),
            $this->dedupTtl
        );
    }

    protected function dispatchPreparedEvent(PreparedEvent $event, int $producerDedupTtl): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->dispatcher->dispatch(function () use ($event, $producerDedupTtl): void {
            $lease = $this->deduplicator->acquire($event->dedupKey(), $producerDedupTtl);
            if (!$lease->isAllowed()) {
                $this->log('data-mid-dedup', 'Event suppressed', 'info', [
                    'event_type' => $event->eventType(),
                    'reason' => $lease->reason(),
                ]);
                return;
            }
            if ($lease->reason() === 'store_unavailable') {
                $this->log('data-mid-dedup', 'Deduplication bypassed', 'warning', [
                    'event_type' => $event->eventType(),
                    'reason' => $lease->reason(),
                ]);
            }

            try {
                $response = $this->client->reportEvent($event->payload());
                if ($response->statusCode() !== 200) {
                    $this->deduplicator->release($lease);
                    $this->log('data-mid', 'Event report rejected', 'error', [
                        'event_type' => $event->eventType(),
                        'status' => $response->statusCode(),
                    ]);
                    return;
                }

                $this->deduplicator->markSucceeded($lease, $producerDedupTtl);
            } catch (Throwable $exception) {
                $this->deduplicator->release($lease);
                $this->log('data-mid', 'Event report failed', 'error', [
                    'event_type' => $event->eventType(),
                    'event_id' => $event->eventId(),
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }

    /** @param array<int, array<string, mixed>> $events */
    protected function reportBatchSynchronously(array $events, string $eventType): bool
    {
        try {
            $result = $this->client->reportBatchResult($events, $this->batchTimeout);
            if ($result->isComplete(count($events))) {
                return true;
            }

            $this->log('data-mid-batch', 'Batch report incomplete', 'error', [
                'event_type' => $eventType,
                'status' => $result->statusCode(),
                'expected' => count($events),
                'accepted' => $result->accepted(),
                'rejected' => $result->rejected(),
                'first_error' => $result->firstError(),
            ]);
            return false;
        } catch (Throwable $exception) {
            $this->log('data-mid-batch', 'Batch report failed', 'error', [
                'event_type' => $eventType,
                'count' => count($events),
                'error' => $exception->getMessage(),
            ]);
            return false;
        }
    }

    /** @param array<string, mixed> $context */
    private function log(string $channel, string $message, string $level, array $context): void
    {
        if ($this->logger !== null) {
            ($this->logger)($channel, $message, $level, $context);
        }
    }
}
