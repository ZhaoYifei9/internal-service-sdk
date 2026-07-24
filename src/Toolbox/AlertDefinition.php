<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Toolbox;

final class AlertDefinition
{
    private string $key;

    private string $id;

    private string $description;

    public function __construct(string $key, string $id, string $description = '')
    {
        $this->key = trim($key);
        $this->id = trim($id);
        $this->description = trim($description);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function description(): string
    {
        return $this->description;
    }
}
