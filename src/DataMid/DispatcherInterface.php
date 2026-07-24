<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\DataMid;

interface DispatcherInterface
{
    public function dispatch(callable $task): void;
}
