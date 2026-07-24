<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\DataMid;

final class CallableDispatcher implements DispatcherInterface
{
    /** @var callable */
    private $dispatcher;

    public function __construct(callable $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function dispatch(callable $task): void
    {
        ($this->dispatcher)($task);
    }
}
