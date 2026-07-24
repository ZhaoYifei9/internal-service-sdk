<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Aj;

use InvalidArgumentException;

final class AjDeviceRegistration
{
    private string $requestId;

    private int $appId;

    private string $deviceUuid;

    private string $aaid;

    private int $installTime;

    private string $fireAppInstanceId;

    public function __construct(
        string $requestId,
        int $appId,
        string $deviceUuid,
        string $aaid,
        int $installTime,
        string $fireAppInstanceId = ''
    ) {
        if (trim($requestId) === '') {
            throw new InvalidArgumentException('AJ request ID is required');
        }
        if ($appId <= 0) {
            throw new InvalidArgumentException('AJ app ID must be positive');
        }
        if (trim($aaid) === '') {
            throw new InvalidArgumentException('AJ advertising ID is required');
        }

        $this->requestId = $requestId;
        $this->appId = $appId;
        $this->deviceUuid = $deviceUuid;
        $this->aaid = $aaid;
        $this->installTime = $installTime;
        $this->fireAppInstanceId = $fireAppInstanceId;
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'requestId' => $this->requestId,
            'appId' => $this->appId,
            'deviceUUID' => $this->deviceUuid,
            'aaid' => $this->aaid,
            'installTime' => $this->installTime,
            'fireAppInstanceId' => $this->fireAppInstanceId,
        ];
    }
}
