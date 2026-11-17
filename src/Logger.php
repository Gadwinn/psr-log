<?php

declare(strict_types=1);

namespace G\Log;

use DateTimeInterface;
use G\Log\Output\{LogOutputErrorLog, LogOutputInterface};
use Psr\Log\{AbstractLogger, InvalidArgumentException, LogLevel};
use Stringable;
use Throwable;

/**
 * PSR-3 logger with a minimum severity threshold and pluggable outputs.
 */
class Logger extends AbstractLogger
{
    /**
     * Every PSR-3 level, from the least to the most severe.
     */
    protected const LEVELS = [
        LogLevel::DEBUG,
        LogLevel::INFO,
        LogLevel::NOTICE,
        LogLevel::WARNING,
        LogLevel::ERROR,
        LogLevel::CRITICAL,
        LogLevel::ALERT,
        LogLevel::EMERGENCY,
    ];

    protected const REGEX_KEY_NAME = '/^[\w\.\-]+$/';

    /**
     * @var LogOutputInterface[]
     */
    protected array $logOutputs;

    /**
     * @var mixed[]
     */
    protected array $levelsEnabled = [];

    /**
     * @param mixed $level Minimum level to log, one of the LogLevel constants.
     * @param LogOutputInterface[] $logOutputs Outputs to write to, the PHP system logger by default.
     *
     * @throws InvalidArgumentException If the level is not a PSR-3 level.
     */
    public function __construct($level = LogLevel::INFO, array $logOutputs = [])
    {
        $threshold = array_search($level, self::LEVELS, true);
        if ($threshold === false) {
            throw new InvalidArgumentException(sprintf('The level "%s" is unknown', print_r($level, true)));
        }
        $this->levelsEnabled = array_slice(self::LEVELS, $threshold);

        $this->logOutputs = $logOutputs !== [] ? $logOutputs : [new LogOutputErrorLog()];
    }

    /**
     * @param mixed $level
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @throws InvalidArgumentException If the level is not a PSR-3 level.
     */
    #[\Override]
    public function log(
        $level,
        string|Stringable $message,
        array $context = []
    ): void {
        if (!in_array($level, self::LEVELS, true)) {
            throw new InvalidArgumentException(sprintf('The level "%s" is unknown', print_r($level, true)));
        }

        if (!in_array($level, $this->levelsEnabled, true)) {
            return;
        }

        $messageInterpolated = self::interpolate((string) $message, $context);

        foreach ($this->logOutputs as $logOutput) {
            $logOutput->writeLog($level, $messageInterpolated, $context);
        }
    }

    /* --- */

    /**
     * Interpolates context values into the message placeholders.
     *
     * @param string $message
     * @param mixed[] $context
     *
     * @return string
     */
    protected static function interpolate(string $message, array $context = []): string
    {
        $replace = [];

        foreach ($context as $key => $val) {
            if (!preg_match(self::REGEX_KEY_NAME, (string) $key)) {
                continue;
            }

            if (is_object($val)) {
                if ($val instanceof Throwable) {
                    $val = sprintf('[exception %s: %s]', get_class($val), $val->getMessage());
                } elseif ($val instanceof Stringable) {
                    try {
                        $val = (string) $val;
                    } catch (Throwable) {
                        // PSR-3 §1.3: a context value must never throw.
                        $val = sprintf('[object %s]', get_class($val));
                    }
                } elseif ($val instanceof DateTimeInterface) {
                    $val = $val->format(DateTimeInterface::RFC3339);
                } else {
                    $val = sprintf('[object %s]', get_class($val));
                }
            } elseif (is_array($val)) {
                $encoded = json_encode($val);
                $val = $encoded !== false ? $encoded : sprintf('[array(%d)]', count($val));
            } elseif (is_bool($val)) {
                $val = ($val ? '[true]' : '[false]');
            } elseif (is_null($val)) {
                $val = strval('[null]');
            } elseif (is_float($val) && is_nan($val)) {
                // Casting NAN to string raises a warning since PHP 8.5.
                $val = strval('NAN');
            } elseif (is_scalar($val)) {
                $val = (string) $val;
            } else {
                $val = sprintf('[%s]', gettype($val));
            }

            $replace['{' . $key . '}'] = $val;
        }

        return strtr($message, $replace);
    }
}
