<?php

declare(strict_types=1);

namespace G\Log\Tests\Output;

use G\Log\Output\LogOutputErrorLog;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class LogOutputErrorLogTest extends TestCase
{
    public function testWritesToFileDestination(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'log-test-');
        $this->assertNotFalse($tmpFile);

        try {
            $output = new LogOutputErrorLog(
                messageType: LogOutputErrorLog::TYPE_FILE,
                destination: $tmpFile,
                format: '%1$-9s: %2$s' . PHP_EOL
            );
            $output->writeLog(LogLevel::WARNING, 'error_log file test message');

            $content = (string) file_get_contents($tmpFile);
            $this->assertStringContainsString(LogLevel::WARNING, $content);
            $this->assertStringContainsString('error_log file test message', $content);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testAppendsMultipleLinesToFileDestination(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'log-test-');
        $this->assertNotFalse($tmpFile);

        try {
            $output = new LogOutputErrorLog(
                messageType: LogOutputErrorLog::TYPE_FILE,
                destination: $tmpFile,
                format: '%1$-9s: %2$s' . PHP_EOL
            );
            $output->writeLog(LogLevel::INFO, 'first');
            $output->writeLog(LogLevel::ERROR, 'second');

            $content = (string) file_get_contents($tmpFile);
            $this->assertStringContainsString('first', $content);
            $this->assertStringContainsString('second', $content);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testUsesSystemLoggerByDefault(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'log-test-');
        $this->assertNotFalse($tmpFile);

        $previousErrorLog = ini_set('error_log', $tmpFile);
        $this->assertNotFalse($previousErrorLog);

        try {
            $output = new LogOutputErrorLog();
            $output->writeLog(LogLevel::NOTICE, 'system logger test message');

            $content = (string) file_get_contents($tmpFile);
            $this->assertStringContainsString('system logger test message', $content);
        } finally {
            ini_set('error_log', $previousErrorLog);
            unlink($tmpFile);
        }
    }
}
