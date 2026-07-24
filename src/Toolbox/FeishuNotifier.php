<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Toolbox;

use Throwable;

final class FeishuNotifier
{
    private FeishuAlertClient $client;

    private AlertCatalog $catalog;

    private bool $fallbackEnabled;

    private string $fallbackSeverity;

    private string $fallbackAt;

    public function __construct(
        FeishuAlertClient $client,
        AlertCatalog $catalog,
        bool $fallbackEnabled = true,
        string $fallbackSeverity = 'P1',
        string $fallbackAt = ''
    ) {
        $this->client = $client;
        $this->catalog = $catalog;
        $this->fallbackEnabled = $fallbackEnabled;
        $this->fallbackSeverity = trim($fallbackSeverity);
        $this->fallbackAt = trim($fallbackAt);
    }

    /** @param array<string, mixed> $data */
    public function notify(string $alertKey, array $data = [], int $system = 0): FeishuNotifyResult
    {
        $definition = $this->catalog->get($alertKey);

        try {
            return new FeishuNotifyResult(
                $this->client->sendAlert($definition->id(), $data, $system)
            );
        } catch (Throwable $exception) {
            if (!$this->fallbackEnabled) {
                return new FeishuNotifyResult([], [], $exception->getMessage(), false);
            }

            $fallbackResponse = [];
            try {
                $fallbackResponse = $this->client->sendCustom(
                    $this->fallbackPayload($definition, $exception)
                );
            } catch (Throwable $fallbackException) {
                // Preserve the primary failure as the actionable result.
            }

            return new FeishuNotifyResult([], $fallbackResponse, $exception->getMessage(), true);
        }
    }

    public function catalog(): AlertCatalog
    {
        return $this->catalog;
    }

    /** @return array<string, string> */
    private function fallbackPayload(AlertDefinition $definition, Throwable $exception): array
    {
        $details = [
            'message' => 'Feishu alert delivery failed',
            'error' => $exception->getMessage(),
            'alert' => [
                'id' => $definition->id(),
                'description' => $definition->description(),
            ],
        ];
        $content = sprintf(
            "``` JSON\n%s\n```",
            json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        $payload = [
            'content' => $content,
            'severity' => $this->fallbackSeverity,
        ];
        if ($this->fallbackAt !== '') {
            $payload['at'] = $this->fallbackAt;
        }

        return $payload;
    }
}
