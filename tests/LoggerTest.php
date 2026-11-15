<?php

declare(strict_types=1);

namespace G\Log\Tests;

use Exception;
use G\Log\Logger;
use G\Log\Output\LogOutputInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\{InvalidArgumentException, LogLevel};
use ReflectionClass;

final class LoggerTest extends TestCase
{
    /** @var list<array{level: string, message: string}> */
    private array $captured = [];

    protected function setUp(): void
    {
        $this->captured = [];
    }

    /**
     * Creates a spy output that writes every entry into $this->captured.
     */
    private function createSpy(): LogOutputInterface
    {
        $ref = &$this->captured;

        return new class ($ref) implements LogOutputInterface {
            /** @param list<array{level: string, message: string}> $captured */
            public function __construct(private array &$captured)
            { //
            }

            public function writeLog($level, string $message): void
            {
                $this->captured[] = ['level' => $level, 'message' => $message];
            }
        };
    }

    // -------------------------------------------------------------------------
    // Level filtering
    // -------------------------------------------------------------------------

    public function testDebugThresholdEnablesAllLevels(): void
    {
        $logger = new Logger(LogLevel::DEBUG, [$this->createSpy()]);
        $levels = (new ReflectionClass(LogLevel::class))->getConstants();

        foreach ($levels as $level) {
            $logger->log($level, 'msg');
        }

        $this->assertCount(count($levels), $this->captured);
    }

    public function testLevelsBelowThresholdAreSuppressed(): void
    {
        $logger = new Logger(LogLevel::ERROR, [$this->createSpy()]);

        $logger->log(LogLevel::DEBUG, 'debug');
        $logger->log(LogLevel::INFO, 'info');
        $logger->log(LogLevel::NOTICE, 'notice');
        $logger->log(LogLevel::WARNING, 'warning');

        $this->assertEmpty($this->captured);
    }

    public function testLevelsAtOrAboveThresholdAreLogged(): void
    {
        $logger = new Logger(LogLevel::ERROR, [$this->createSpy()]);

        $logger->log(LogLevel::ERROR, 'error');
        $logger->log(LogLevel::CRITICAL, 'critical');
        $logger->log(LogLevel::ALERT, 'alert');
        $logger->log(LogLevel::EMERGENCY, 'emergency');

        $this->assertCount(4, $this->captured);
    }

    public function testEmergencyThresholdOnlyLogsEmergency(): void
    {
        $logger = new Logger(LogLevel::EMERGENCY, [$this->createSpy()]);

        $logger->log(LogLevel::DEBUG, 'debug');
        $logger->log(LogLevel::ERROR, 'error');
        $logger->log(LogLevel::ALERT, 'alert');
        $this->assertEmpty($this->captured);

        $logger->log(LogLevel::EMERGENCY, 'emergency');
        $this->assertCount(1, $this->captured);
        $this->assertSame(LogLevel::EMERGENCY, $this->captured[0]['level']);
    }

    // -------------------------------------------------------------------------
    // Level validation
    // -------------------------------------------------------------------------

    public function testUnknownLevelThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $logger = new Logger(LogLevel::DEBUG, [$this->createSpy()]);
        $logger->log('not_a_real_level', 'message');
    }

    public function testUnknownLevelIsRejectedEvenWhenBelowThreshold(): void
    {
        $logger = new Logger(LogLevel::EMERGENCY, [$this->createSpy()]);

        $this->expectException(InvalidArgumentException::class);
        $logger->log('verbose', 'message');
    }

    public function testNonStringLevelThrowsInvalidArgumentException(): void
    {
        $logger = new Logger(LogLevel::DEBUG, [$this->createSpy()]);

        $this->expectException(InvalidArgumentException::class);
        $logger->log(3, 'message');
    }

    public function testUnknownThresholdThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Logger('not_a_real_level', [$this->createSpy()]);
    }

    // -------------------------------------------------------------------------
    // PSR-3 shorthand methods and message types
    // -------------------------------------------------------------------------

    public function testShorthandMethodsLogWithTheirOwnLevel(): void
    {
        $logger = new Logger(LogLevel::DEBUG, [$this->createSpy()]);

        $logger->emergency('m');
        $logger->alert('m');
        $logger->critical('m');
        $logger->error('m');
        $logger->warning('m');
        $logger->notice('m');
        $logger->info('m');
        $logger->debug('m');

        $this->assertSame(
            [
                LogLevel::EMERGENCY,
                LogLevel::ALERT,
                LogLevel::CRITICAL,
                LogLevel::ERROR,
                LogLevel::WARNING,
                LogLevel::NOTICE,
                LogLevel::INFO,
                LogLevel::DEBUG,
            ],
            array_column($this->captured, 'level')
        );
    }

    public function testStringableMessageIsAccepted(): void
    {
        $logger  = new Logger(LogLevel::DEBUG, [$this->createSpy()]);
        $message = new class implements \Stringable {
            public function __toString(): string
            {
                return 'Hello {name}';
            }
        };
        $logger->info($message, ['name' => 'Bob']);

        $this->assertSame('Hello Bob', $this->captured[0]['message']);
    }

    // -------------------------------------------------------------------------
    // Context interpolation
    // -------------------------------------------------------------------------

    public function testPlaceholderWithoutContextValueIsLeftUntouched(): void
    {
        $logger = new Logger(LogLevel::DEBUG, [$this->createSpy()]);
        $logger->info('Hello {name}', ['other' => 'x']);

        $this->assertSame('Hello {name}', $this->captured[0]['message']);
    }

    public function testContextValuesNeverThrowNorRaiseErrors(): void
    {
        $throwing = new class implements \Stringable {
            public function __toString(): string
            {
                throw new \RuntimeException('boom');
            }
        };
        $resource = fopen('php://memory', 'r');
        $this->assertIsResource($resource);

        $errors = [];
        set_error_handler(static function (int $no, string $str) use (&$errors): bool {
            $errors[] = $str;

            return true;
        });

        try {
            $logger = new Logger(LogLevel::DEBUG, [$this->createSpy()]);
            $logger->info('{a} {b} {c} {d} {e} {f}', [
                'a' => $throwing,
                'b' => static fn (): int => 1,
                'c' => $resource,
                'd' => NAN,
                'e' => ["\xB1\x31"],
                'f' => new \stdClass(),
            ]);
        } finally {
            restore_error_handler();
            fclose($resource);
        }

        $this->assertSame([], $errors);
        $this->assertCount(1, $this->captured);
        $this->assertStringNotContainsString('{a}', $this->captured[0]['message']);
        $this->assertStringContainsString('[array(1)]', $this->captured[0]['message']);
    }

    public function testInterpolationReplacesStringPlaceholders(): void
    {
        $logger = new Logger(LogLevel::DEBUG, [$this->createSpy()]);
        $logger->log(LogLevel::INFO, 'Hello {name}, age {age}.', ['name' => 'Alice', 'age' => 30]);

        $this->assertSame('Hello Alice, age 30.', $this->captured[0]['message']);
    }

    public function testInterpolationIgnoresKeysWithInvalidChars(): void
    {
        $logger = new Logger(LogLevel::DEBUG, [$this->createSpy()]);
        $logger->log(LogLevel::DEBUG, 'Hello {in valid}', ['in valid' => 'Alice']);

        $this->assertSame('Hello {in valid}', $this->captured[0]['message']);
    }

    public function testInterpolationFormatsThrowableBeforeStringable(): void
    {
        $logger    = new Logger(LogLevel::DEBUG, [$this->createSpy()]);
        $exception = new Exception('Something went wrong');
        $logger->log(LogLevel::ERROR, '{err}', ['err' => $exception]);

        $this->assertStringContainsString(
            '[exception Exception: Something went wrong]',
            $this->captured[0]['message']
        );
    }

    public function testInterpolationFormatsStringableObject(): void
    {
        $logger = new Logger(LogLevel::DEBUG, [$this->createSpy()]);
        $obj    = new class {
            public function __toString(): string
            {
                return 'stringable-value';
            }
        };
        $logger->log(LogLevel::INFO, 'Value: {obj}', ['obj' => $obj]);

        $this->assertSame('Value: stringable-value', $this->captured[0]['message']);
    }

    public function testInterpolationFormatsBooleans(): void
    {
        $logger = new Logger(LogLevel::DEBUG, [$this->createSpy()]);
        $logger->log(LogLevel::DEBUG, '{t} {f}', ['t' => true, 'f' => false]);

        $this->assertSame('[true] [false]', $this->captured[0]['message']);
    }

    public function testInterpolationFormatsNull(): void
    {
        $logger = new Logger(LogLevel::DEBUG, [$this->createSpy()]);
        $logger->log(LogLevel::DEBUG, 'val={v}', ['v' => null]);

        $this->assertSame('val=[null]', $this->captured[0]['message']);
    }

    public function testInterpolationFormatsArrayAsJson(): void
    {
        $logger = new Logger(LogLevel::DEBUG, [$this->createSpy()]);
        $logger->log(LogLevel::DEBUG, 'data={d}', ['d' => ['x' => 1, 'y' => 2]]);

        $this->assertSame('data={"x":1,"y":2}', $this->captured[0]['message']);
    }

    // -------------------------------------------------------------------------
    // Multiple outputs
    // -------------------------------------------------------------------------

    public function testMultipleOutputsAllReceiveLogEntry(): void
    {
        $secondCapture = [];
        $ref           = &$secondCapture;

        $secondSpy = new class ($ref) implements LogOutputInterface {
            public function __construct(private array &$captured)
            { //
            }

            public function writeLog($level, string $message): void
            {
                $this->captured[] = ['level' => $level, 'message' => $message];
            }
        };

        $logger = new Logger(LogLevel::INFO, [$this->createSpy(), $secondSpy]);
        $logger->log(LogLevel::INFO, 'broadcast');

        $this->assertCount(1, $this->captured);
        $this->assertCount(1, $secondCapture);
        $this->assertSame('broadcast', $this->captured[0]['message']);
        $this->assertSame('broadcast', $secondCapture[0]['message']);
    }
}
