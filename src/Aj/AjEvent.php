<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Aj;

use InvalidArgumentException;

final class AjEvent
{
    /** @var array<string, int|string> */
    private array $payload;

    public function __construct(
        string $requestId,
        string $mobile,
        string $idNumber,
        string $sn,
        string $risk,
        int $appId,
        int $originalAppId,
        string $deviceUuid,
        string $eventName,
        int $eventTime,
        string $clientVersion
    ) {
        if (trim($requestId) === '') {
            throw new InvalidArgumentException('AJ request ID is required');
        }
        if ($appId <= 0) {
            throw new InvalidArgumentException('AJ app ID must be positive');
        }
        if (trim($eventName) === '') {
            throw new InvalidArgumentException('AJ event name is required');
        }
        if (trim($clientVersion) === '') {
            throw new InvalidArgumentException('AJ client version is required');
        }

        $this->payload = [
            'requestId' => $requestId,
            'mobile' => $mobile,
            'idNumber' => $idNumber,
            'sn' => $sn,
            'risk' => $risk,
            'appId' => $appId,
            'originalAppId' => $originalAppId,
            'deviceUUID' => $deviceUuid,
            'eventName' => $eventName,
            'eventTime' => $eventTime,
            'clientVersion' => $clientVersion,
        ];
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return $this->payload;
    }
}
