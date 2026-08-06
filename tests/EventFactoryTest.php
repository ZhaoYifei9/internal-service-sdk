<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Tests;

use Internal\ServiceSdk\DataMid\EventFactory;
use Internal\ServiceSdk\DataMid\EventId;
use Internal\ServiceSdk\DataMid\EventType;
use PHPUnit\Framework\TestCase;

final class EventFactoryTest extends TestCase
{
    public function testActiveEventUsesWindowIdAndRollingDedupKey(): void
    {
        $event = $this->factory()->userActive('1001', '5001', 'test-phone', 600);
        $payload = $event->payload();

        self::assertSame(EventType::USER_ACTIVE, $event->eventType());
        self::assertSame('NG', $payload['country_code']);
        self::assertSame(1784347200, $payload['timestamp']);
        self::assertSame(1784347200, $payload['data']['active_at']);
        self::assertSame(
            EventId::active('NG', '5001', '1001', 1784347200, 600),
            $event->eventId()
        );
        self::assertSame(
            EventId::activeDedupKey('NG', '5001', '1001'),
            $event->dedupKey()
        );
    }

    public function testApplicationStatusAllowsOnlyMessageOrderContext(): void
    {
        $event = $this->factory()->applicationStatusChanged(
            '1001',
            '5001',
            'test-phone',
            'ORDER-A',
            'settled',
            1784347100,
            [
                'product_name' => 'Quick Loan',
                'bank_last4' => '1234-5678-9012',
                'full_bank_account' => 'must-not-leave',
            ]
        );

        $data = $event->payload()['data'];
        self::assertSame('SETTLED', $data['status']);
        self::assertSame('Quick Loan', $data['product_name']);
        self::assertSame('9012', $data['bank_last4']);
        self::assertArrayNotHasKey('full_bank_account', $data);
    }

    public function testLeadAndOverduePayloadsUseTheFactoryClock(): void
    {
        $factory = $this->factory();
        $lead = $factory->registrationStarted('5001', 'test-phone');
        $overdue = $factory->overdueDaily(
            '1001',
            '5001',
            'test-phone',
            'ORDER-A',
            3,
            5,
            '2026-07-24'
        );

        self::assertSame('OTP_REGISTER', $lead->payload()['data']['source']);
        self::assertSame(1784347200, $lead->payload()['data']['started_at']);
        self::assertSame('2026-07-24', $overdue->payload()['data']['batch_date']);
        self::assertSame(5, $overdue->payload()['data']['max_overdue_days']);
    }

    public function testNamedFactoriesCoverTheStableEventContract(): void
    {
        $factory = $this->factory();
        $events = [
            $factory->userRegistered('1001', '5001', ['phone' => 'test-phone']),
            $factory->userAssetSynced('1001', '5001', 'test-phone', [
                'package_app_id' => '5002',
                'sync_reason' => 'login',
                'source_updated_at' => 1784347100,
            ]),
            $factory->userActive('1001', '5001', 'test-phone', 600),
            $factory->profileUpdated('1001', '5001', 'test-phone', ['city' => 'Lagos'], 3600),
            $factory->profileCompleted('1001', '5001', 'test-phone'),
            $factory->whatsappUpdated('1001', '5001', 'test-phone', 'VALID', 'test-wa', 3600),
            $factory->deregistered('1001', '5001', 'test-phone', 1, 'reason'),
            $factory->lifecycleChanged('1001', '5001', 'test-phone', 'SETTLED', 'ORDER-A', 'NORMAL'),
            $factory->overdueDaily('1001', '5001', 'test-phone', 'ORDER-A', 3, 5, '2026-07-24'),
            $factory->identityDeviceObserved(
                '1001',
                '5001',
                'test-phone',
                'install-uuid',
                'android',
                'gaid',
                'organic'
            ),
            $factory->registrationStarted('5001', 'test-phone'),
            $factory->applicationSubmitted('1001', '5001', 'test-phone', 'ORDER-A'),
            $factory->applicationStatusChanged(
                '1001',
                '5001',
                'test-phone',
                'ORDER-A',
                'APPROVED',
                1784347100
            ),
            $factory->repaymentScheduled(
                '1001',
                '5001',
                'test-phone',
                'ORDER-A',
                1784952000,
                1500.0
            ),
        ];

        self::assertSame(EventType::all(), array_map(
            static function ($event): string {
                return $event->eventType();
            },
            $events
        ));
        foreach ($events as $event) {
            self::assertNotSame('', $event->eventId());
            self::assertSame('NG', $event->payload()['country_code']);
        }
    }

    public function testUserAssetSyncKeepsLogicalAndPackageAppsSeparate(): void
    {
        $event = $this->factory()->userAssetSynced('1001', '5001', 'test-phone', [
            'package_app_id' => '5002',
            'sync_reason' => 'login',
            'source_updated_at' => 1784347100,
            'device_id' => 'install-1',
        ]);
        $payload = $event->payload();

        self::assertSame(EventType::USER_ASSET_SYNCED, $payload['event_type']);
        self::assertSame('5001', $payload['app_id']);
        self::assertSame('5002', $payload['data']['package_app_id']);
        self::assertSame('LOGIN', $payload['data']['sync_reason']);
        self::assertSame(1784347100, $payload['timestamp']);
    }

    private function factory(): EventFactory
    {
        return new EventFactory(
            'ng',
            static function (): int {
                return 1784347200;
            },
            static function (): string {
                return 'nonce-test';
            }
        );
    }
}
