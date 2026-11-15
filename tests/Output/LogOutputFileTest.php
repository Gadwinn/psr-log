<?php

declare(strict_types=1);

namespace G\Log\Tests\Output;

use G\Log\Output\LogOutputFile;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class LogOutputFileTest extends TestCase
{
    public function testWritesFormattedLine(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'log-test-');
        $this->assertNotFalse($tmpFile);

        try {
            $output = new LogOutputFile($tmpFile);
            $output->writeLog(LogLevel::WARNING, 'file test message');

            $content = (string) file_get_contents($tmpFile);
            $this->assertStringContainsString(LogLevel::WARNING, $content);
            $this->assertStringContainsString('file test message', $content);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testCustomFormatReceivesDateLevelAndMessage(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'log-test-');
        $this->assertNotFalse($tmpFile);

        try {
            $output = new LogOutputFile($tmpFile, '[%2$s] %3$s|%1$s');
            $output->writeLog(LogLevel::ERROR, 'custom');

            $content = (string) file_get_contents($tmpFile);
            $this->assertMatchesRegularExpression('/^\[error\] custom\|\d{4}-\d{2}-\d{2}T/', $content);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testAppendsMultipleLines(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'log-test-');
        $this->assertNotFalse($tmpFile);

        try {
            $output = new LogOutputFile($tmpFile);
            $output->writeLog(LogLevel::INFO, 'first');
            $output->writeLog(LogLevel::ERROR, 'second');

            $content = (string) file_get_contents($tmpFile);
            $this->assertStringContainsString('first', $content);
            $this->assertStringContainsString('second', $content);
        } finally {
            unlink($tmpFile);
        }
    }
}
