<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Toolbox;

use Internal\ServiceSdk\Exception\InternalServiceException;

final class AlertCatalog
{
    /** @var array<string, AlertDefinition> */
    private array $definitions = [];

    /**
     * @param array<string, array{id:string,description?:string,desc?:string}|string> $definitions
     */
    public function __construct(array $definitions)
    {
        foreach ($definitions as $key => $definition) {
            $id = is_array($definition) ? (string) ($definition['id'] ?? '') : (string) $definition;
            $description = is_array($definition)
                ? (string) ($definition['description'] ?? $definition['desc'] ?? '')
                : '';
            $item = new AlertDefinition((string) $key, $id, $description);
            if ($item->key() === '' || $item->id() === '') {
                throw new InternalServiceException('Feishu alert catalog contains an empty key or ID');
            }
            $this->definitions[$item->key()] = $item;
        }
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function get(string $key): AlertDefinition
    {
        if (!$this->has($key)) {
            throw new InternalServiceException(sprintf('Unknown Feishu alert key: %s', $key));
        }

        return $this->definitions[$key];
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->definitions);
    }

    public function description(string $keyOrId, string $fallback = ''): string
    {
        if ($this->has($keyOrId)) {
            return $this->definitions[$keyOrId]->description() ?: $fallback;
        }

        foreach ($this->definitions as $definition) {
            if ($definition->id() === $keyOrId) {
                return $definition->description() ?: $fallback;
            }
        }

        return $fallback;
    }
}
