<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Notification;

use Internal\ServiceSdk\Exception\InternalServiceException;

/**
 * Validated service-notification device registration contract.
 */
final class DeviceRegistration
{
    public const PLATFORM_ANDROID = 'ANDROID';

    public const PLATFORM_IOS = 'IOS';

    private string $installUuid;

    private string $countryCode;

    private string $appId;

    private string $platform;

    private string $fcmToken;

    private string $tokenUpdatedAt;

    private ?string $aaid;

    public function __construct(
        string $installUuid,
        string $countryCode,
        string $appId,
        string $platform,
        string $fcmToken,
        string $tokenUpdatedAt,
        ?string $aaid = null
    ) {
        $installUuid = trim($installUuid);
        if ($installUuid === '' || strlen($installUuid) > 128 || self::hasControlCharacter($installUuid)) {
            throw new InternalServiceException('invalid notification install UUID');
        }

        $countryCode = strtoupper(trim($countryCode));
        if (!preg_match('/^[A-Z]{2,3}$/', $countryCode)) {
            throw new InternalServiceException('invalid notification country code');
        }

        $appId = trim($appId);
        if ($appId === '' || strlen($appId) > 32 || self::hasControlCharacter($appId)) {
            throw new InternalServiceException('invalid notification app ID');
        }

        $platform = strtoupper(trim($platform));
        if (!in_array($platform, [self::PLATFORM_ANDROID, self::PLATFORM_IOS], true)) {
            throw new InternalServiceException('invalid notification platform');
        }

        $fcmToken = trim($fcmToken);
        if (strlen($fcmToken) < 20 || strlen($fcmToken) > 4096) {
            throw new InternalServiceException('invalid notification FCM token');
        }

        $tokenUpdatedAt = trim($tokenUpdatedAt);
        if (!preg_match(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/',
            $tokenUpdatedAt
        )) {
            throw new InternalServiceException('invalid notification token update time');
        }
        try {
            new \DateTimeImmutable($tokenUpdatedAt);
        } catch (\Throwable $exception) {
            throw new InternalServiceException('invalid notification token update time');
        }
        $dateErrors = \DateTimeImmutable::getLastErrors();
        if ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) {
            throw new InternalServiceException('invalid notification token update time');
        }

        $aaid = $aaid === null ? null : trim($aaid);
        if ($aaid === '') {
            $aaid = null;
        }
        if ($aaid !== null && (strlen($aaid) > 191 || self::hasControlCharacter($aaid))) {
            throw new InternalServiceException('invalid notification AAID');
        }

        $this->installUuid = $installUuid;
        $this->countryCode = $countryCode;
        $this->appId = $appId;
        $this->platform = $platform;
        $this->fcmToken = $fcmToken;
        $this->tokenUpdatedAt = $tokenUpdatedAt;
        $this->aaid = $aaid;
    }

    public function installUuid(): string
    {
        return $this->installUuid;
    }

    /** @return array{country_code:string,app_id:string,platform:string,fcm_token:string,aaid:?string,token_updated_at:string} */
    public function payload(): array
    {
        return [
            'country_code' => $this->countryCode,
            'app_id' => $this->appId,
            'platform' => $this->platform,
            'fcm_token' => $this->fcmToken,
            'aaid' => $this->aaid,
            'token_updated_at' => $this->tokenUpdatedAt,
        ];
    }

    private static function hasControlCharacter(string $value): bool
    {
        return preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
    }
}
