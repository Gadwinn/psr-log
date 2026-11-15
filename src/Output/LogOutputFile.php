<?php

declare(strict_types=1);

namespace G\Log\Output;

class LogOutputFile implements LogOutputInterface
{
    public function __construct(
        protected string $filename,
        protected string $format = '%1$s %2$-9s: %3$s' . PHP_EOL
    ) { //
    }

    public function writeLog($level, string $message): void
    {
        $line = sprintf(
            $this->format,
            date('c'),
            print_r(
                $level,
                true
            ),
            $message
        );
        file_put_contents($this->filename, $line, FILE_APPEND | LOCK_EX);
    }
}
