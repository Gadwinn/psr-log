<?php

declare(strict_types=1);

namespace G\Log\Output;

interface LogOutputInterface
{
    /**
     * @param mixed $level
     * @param string $message Message with its placeholders already interpolated.
     * @param mixed[] $context Raw PSR-3 context; the "exception" key may hold a Throwable.
     *
     * @return void
     */
    public function writeLog($level, string $message, array $context = []): void;
}
