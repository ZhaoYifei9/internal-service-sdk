<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\DataMid;

final class InlineDispatcher implements DispatcherInterface
{
    public function dispatch(callable $task): void
    {
        $task();
    }
}
