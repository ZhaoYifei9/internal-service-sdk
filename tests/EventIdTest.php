<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Tests;

use Internal\ServiceSdk\DataMid\EventId;
use Internal\ServiceSdk\DataMid\EventType;
use PHPUnit\Framework\TestCase;

final class EventIdTest extends TestCase
{
    public function testSnapshotIsIndependentOfAssociativeKeyOrder(): void
    {
        $first = EventId::snapshot(
            'NG',
            '5001',
            '1001',
            EventType::USER_PROFILE_UPDATED,
            ['email' => 'a@example.com', 'device' => ['os' => 'android', 'id' => 'device-1']],
            3601,
            3600
        );
        $second = EventId::snapshot(
            'ng',
            '5001',
            '1001',
            EventType::USER_PROFILE_UPDATED,
            ['device' => ['id' => 'device-1', 'os' => 'android'], 'email' => 'a@example.com'],
            3601,
            3600
        );

        self::assertSame($first, $second);
    }

    public function testBusinessDimensionsAndWindowsRemainStable(): void
    {
        $first = EventId::business(
            'NG',
            '5001',
            '1001',
            EventType::LOAN_APPLICATION_STATUS_CHANGED,
            ['ORDER-A', 'SETTLED', 1784110783]
        );
        $retry = EventId::business(
            'ng',
            '5001',
            '1001',
            EventType::LOAN_APPLICATION_STATUS_CHANGED,
            ['ORDER-A', 'SETTLED', 1784110783]
        );
        $otherOrder = EventId::business(
            'NG',
            '5001',
            '1001',
            EventType::LOAN_APPLICATION_STATUS_CHANGED,
            ['ORDER-B', 'SETTLED', 1784110783]
        );

        self::assertSame($first, $retry);
        self::assertNotSame($first, $otherOrder);
        self::assertNotSame(
            EventId::active('NG', '5001', '1001', 601, 600),
            EventId::active('NG', '5001', '1001', 1201, 600)
        );
        self::assertSame(
            EventId::activeDedupKey('NG', '5001', '1001'),
            EventId::activeDedupKey('ng', '5001', '1001')
        );
    }
}
