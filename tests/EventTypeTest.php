<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Tests;

use Internal\ServiceSdk\DataMid\EventType;
use PHPUnit\Framework\TestCase;

final class EventTypeTest extends TestCase
{
    public function testEventCodesAreUniqueAndFollowProtocolFormat(): void
    {
        $eventTypes = EventType::all();

        self::assertNotEmpty($eventTypes);
        self::assertSame(count($eventTypes), count(array_unique($eventTypes)));
        foreach ($eventTypes as $eventType) {
            self::assertSame(1, preg_match('/^[a-z]+(?:\.[a-z_]+)+$/', $eventType));
            self::assertTrue(EventType::isKnown($eventType));
        }
        self::assertFalse(EventType::isKnown('unknown.event'));
    }
}
