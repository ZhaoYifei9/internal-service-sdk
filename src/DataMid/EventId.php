<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\DataMid;

/**
 * Generates stable event IDs without putting raw personal data into cache keys.
 */
final class EventId
{
    /** @param array<int, bool|float|int|string|null> $businessParts */
    public static function business(
        string $countryCode,
        string $appId,
        string $userId,
        string $eventType,
        array $businessParts = []
    ): string {
        return hash('sha256', self::encode([
            'country_code' => strtoupper($countryCode),
            'app_id' => $appId,
            'user_id' => $userId,
            'event_type' => $eventType,
            'business_parts' => $businessParts,
        ]));
    }

    /** @param array<string, mixed> $snapshot */
    public static function snapshot(
        string $countryCode,
        string $appId,
        string $userId,
        string $eventType,
        array $snapshot,
        int $occurredAt,
        int $windowSeconds
    ): string {
        $windowSeconds = max(60, $windowSeconds);

        return self::business($countryCode, $appId, $userId, $eventType, [
            hash('sha256', self::encode($snapshot)),
            (int) floor($occurredAt / $windowSeconds),
        ]);
    }

    public static function active(
        string $countryCode,
        string $appId,
        string $userId,
        int $activeAt,
        int $interval
    ): string {
        $interval = max(60, $interval);

        return self::business($countryCode, $appId, $userId, EventType::USER_ACTIVE, [
            (int) floor($activeAt / $interval),
        ]);
    }

    public static function activeDedupKey(string $countryCode, string $appId, string $userId): string
    {
        return self::business($countryCode, $appId, $userId, EventType::USER_ACTIVE);
    }

    private static function encode(array $value): string
    {
        return (string) json_encode(
            self::normalize($value),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /** @return mixed */
    private static function normalize($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        if (self::isList($value)) {
            return array_map([self::class, 'normalize'], $value);
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = self::normalize($item);
        }

        return $value;
    }

    private static function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
