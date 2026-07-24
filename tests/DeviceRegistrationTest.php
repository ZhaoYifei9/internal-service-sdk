<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Tests;

use Internal\ServiceSdk\Exception\InternalServiceException;
use Internal\ServiceSdk\Notification\DeviceRegistration;
use PHPUnit\Framework\TestCase;

final class DeviceRegistrationTest extends TestCase
{
    public function testNormalizesAndBuildsContractPayload(): void
    {
        $registration = new DeviceRegistration(
            ' install-uuid ',
            ' ng ',
            ' 5001 ',
            ' android ',
            str_repeat('t', 40),
            '2026-07-24T12:00:00.123456+01:00',
            ' gaid-value '
        );

        self::assertSame('install-uuid', $registration->installUuid());
        self::assertSame([
            'country_code' => 'NG',
            'app_id' => '5001',
            'platform' => 'ANDROID',
            'fcm_token' => str_repeat('t', 40),
            'aaid' => 'gaid-value',
            'token_updated_at' => '2026-07-24T12:00:00.123456+01:00',
        ], $registration->payload());
    }

    /**
     * @dataProvider invalidContractProvider
     * @param array<int, mixed> $arguments
     */
    public function testRejectsInvalidContractValues(array $arguments): void
    {
        $this->expectException(InternalServiceException::class);

        new DeviceRegistration(...$arguments);
    }

    /** @return array<string, array{array<int, mixed>}> */
    public function invalidContractProvider(): array
    {
        $valid = [
            'install-uuid',
            'NG',
            '5001',
            DeviceRegistration::PLATFORM_ANDROID,
            str_repeat('t', 40),
            '2026-07-24T12:00:00+01:00',
            null,
        ];

        return [
            'empty install UUID' => [[...$valid, 0 => '']],
            'country is not ISO alpha' => [[...$valid, 1 => 'ExampleCountry']],
            'empty app ID' => [[...$valid, 2 => '']],
            'unknown platform' => [[...$valid, 3 => 'WEB']],
            'short FCM token' => [[...$valid, 4 => 'short']],
            'timestamp has no timezone' => [[...$valid, 5 => '2026-07-24 12:00:00']],
            'timestamp is not a calendar date' => [[...$valid, 5 => '2026-02-31T12:00:00+01:00']],
            'AAID exceeds storage contract' => [[...$valid, 6 => str_repeat('a', 192)]],
        ];
    }
}
