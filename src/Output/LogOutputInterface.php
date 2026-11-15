<?php

declare(strict_types=1);

namespace G\Log\Output;

interface LogOutputInterface
{
    /**
     * @param mixed $level
     * @param string $message
     *
     * @return void
     */
    public function writeLog($level, string $message): void;
}
