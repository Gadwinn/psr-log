<?php

declare(strict_types=1);

namespace G\Log\Output;

use Throwable;

class LogOutputErrorLog implements LogOutputInterface
{
    /** Send to the system logger, as configured by the `error_log` php.ini directive. */
    public const TYPE_SYSTEM = 0;

    /** Send by email to the address given as $destination. */
    public const TYPE_EMAIL = 1;

    /** Append to the file given as $destination. */
    public const TYPE_FILE = 3;

    /** Send directly to the SAPI logging handler. */
    public const TYPE_SAPI = 4;

    /**
     * @param 0|1|3|4 $messageType
     */
    public function __construct(
        protected int $messageType = self::TYPE_SYSTEM,
        protected ?string $destination = null,
        protected ?string $additionalHeaders = null,
        protected string $format = '%1$-9s: %2$s'
    ) { //
    }

    public function writeLog($level, string $message, array $context = []): void
    {
        // PSR-3 §1.3: the "exception" key must be checked before being used as an exception.
        if (($context['exception'] ?? null) instanceof Throwable) {
            $message .= PHP_EOL . $context['exception'];
        }

        $line = sprintf(
            $this->format,
            print_r(
                $level,
                true
            ),
            $message
        );

        error_log(
            $line,
            $this->messageType,
            $this->destination,
            $this->additionalHeaders
        );
    }
}
