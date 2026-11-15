# PSR-3 Logger

A [PSR-3](https://www.php-fig.org/psr/psr-3/) compliant logger with a configurable minimum severity threshold and a pluggable output system.

## Tech Stack

- PHP `>=8.3`
- [`psr/log`](https://packagist.org/packages/psr/log) `^3.0`

## Installation

```bash
composer require gadwinn/psr-log
```

## Usage

### Basic instantiation

The constructor accepts a **minimum log level** and an array of **outputs**.  
Any level below the threshold is silently discarded. Without any output, entries go to the PHP system logger (`LogOutputErrorLog`).

```php
use G\Log\Logger;
use G\Log\Output\LogOutputFile;
use Psr\Log\LogLevel;

// Log INFO and above to a file
$logger = new Logger(
    level: LogLevel::INFO,
    logOutputs: [new LogOutputFile('/var/log/app.log')]
);

$logger->info('User {id} logged in.', ['id' => 42]);
$logger->error('Payment failed.', ['order' => $orderId]);
$logger->debug('This is suppressed.'); // below INFO threshold
```

### Log levels (RFC 5424 severity, highest → lowest)

| Constant | Method shorthand |
|---|---|
| `LogLevel::EMERGENCY` | `$logger->emergency(...)` |
| `LogLevel::ALERT` | `$logger->alert(...)` |
| `LogLevel::CRITICAL` | `$logger->critical(...)` |
| `LogLevel::ERROR` | `$logger->error(...)` |
| `LogLevel::WARNING` | `$logger->warning(...)` |
| `LogLevel::NOTICE` | `$logger->notice(...)` |
| `LogLevel::INFO` | `$logger->info(...)` |
| `LogLevel::DEBUG` | `$logger->debug(...)` |

Setting `LogLevel::DEBUG` enables **all** levels. Setting `LogLevel::ERROR` enables only `ERROR`, `CRITICAL`, `ALERT`, and `EMERGENCY`.

### Context interpolation

Placeholders in messages follow the `{key}` syntax defined by PSR-3.  
Keys must match `[\w\.\-]+` (letters, digits, `_`, `.`, `-`).

```php
$logger->warning('Disk usage at {percent}% on {host}.', [
    'percent' => 92,
    'host'    => 'web-01',
]);
// → 2026-05-18T12:00:00+00:00 warning  : Disk usage at 92% on web-01.
```

Context values are serialized safely regardless of type:

| Type | Rendered as |
|---|---|
| `string`, `int`, `float` | direct cast to string |
| `bool` | `[true]` / `[false]` |
| `null` | `[null]` |
| `array` | JSON — fallback `[array(n)]` on encoding failure |
| `Throwable` | `[exception ClassName: message]` |
| `Stringable` object | result of `__toString()` |
| `DateTimeInterface` | RFC 3339 date, e.g. `2026-05-18T12:00:00+00:00` |
| Other object | `[object ClassName]` |
| Anything else (resource…) | `[type]`, e.g. `[resource]` |

A context value never throws nor raises a PHP error (PSR-3 §1.3).

### Exceptions

Pass the exception in the `exception` context key, as PSR-3 recommends. The built-in outputs append its class, message, location and stack trace after the message:

```php
try {
    $payment->charge();
} catch (Throwable $e) {
    $logger->error('Payment failed for order {order}.', ['order' => 42, 'exception' => $e]);
}
```

### `LogOutputFile` — built-in file output

```php
use G\Log\Output\LogOutputFile;

// Default format: "2026-05-18T12:00:00+00:00 warning  : message\n"
$output = new LogOutputFile('/var/log/app.log');

// Custom sprintf format — %1$s = date, %2$s = level, %3$s = message
$output = new LogOutputFile(
    filename: '/var/log/app.log',
    format: '[%2$s] %1$s — %3$s' . PHP_EOL
);
```

### `LogOutputErrorLog` — built-in `error_log()` output

Routes entries through PHP's native [`error_log()`](https://www.php.net/manual/en/function.error-log.php), so the destination is controlled by the `message_type` argument:

| Constant | `message_type` | Destination |
|---|---|---|
| `LogOutputErrorLog::TYPE_SYSTEM` (default) | `0` | The system logger, as configured by the `error_log` php.ini directive (OS log / SAPI error log) |
| `LogOutputErrorLog::TYPE_EMAIL` | `1` | Emailed to the address given as `$destination`, using `$additionalHeaders` |
| `LogOutputErrorLog::TYPE_FILE` | `3` | Appended to the file given as `$destination` |
| `LogOutputErrorLog::TYPE_SAPI` | `4` | Sent directly to the SAPI logging handler |

```php
use G\Log\Logger;
use G\Log\Output\LogOutputErrorLog;
use Psr\Log\LogLevel;

// Default: send everything to the system logger (php.ini "error_log" directive)
$logger = new Logger(LogLevel::WARNING, [new LogOutputErrorLog()]);

// Append to a specific file via error_log() instead of LogOutputFile
$fileOutput = new LogOutputErrorLog(
    messageType: LogOutputErrorLog::TYPE_FILE,
    destination: '/var/log/app.log',
    format: '%1$-9s: %2$s' . PHP_EOL // error_log() type 3 does not add a trailing newline
);

// Email critical errors
$emailOutput = new LogOutputErrorLog(
    messageType: LogOutputErrorLog::TYPE_EMAIL,
    destination: 'oncall@example.com',
    additionalHeaders: 'From: alerts@example.com'
);

$logger = new Logger(LogLevel::CRITICAL, [$emailOutput]);
$logger->critical('Payment gateway is down.');
```

> `message_type` `2` (SAPI logging without the SAPI's own log) has been removed by PHP itself and is intentionally not exposed here.

### Multiple outputs (fan-out)

```php
use G\Log\Logger;
use G\Log\Output\LogOutputFile;
use Psr\Log\LogLevel;

$logger = new Logger(
    level: LogLevel::WARNING,
    logOutputs: [
        new LogOutputFile('/var/log/app.log'),
        new LogOutputFile('/var/log/errors.log'),
    ]
);
```

Every registered output receives the same entry. There is no upper limit on the number of outputs.

### Custom output driver

Implement `LogOutputInterface` to route log entries anywhere (database, stream, HTTP endpoint, etc.).

```php
use G\Log\Output\LogOutputInterface;

class SyslogOutput implements LogOutputInterface
{
    public function writeLog($level, string $message, array $context = []): void
    {
        syslog(LOG_INFO, "[$level] $message");
    }
}

$logger = new Logger(LogLevel::DEBUG, [new SyslogOutput()]);
```

`$message` is already interpolated; `$context` is the raw PSR-3 context, so an output can store it as structured data or read `$context['exception']`.

```php
```

### Logger-aware classes (`LoggerAwareInterface`)

Use `Psr\Log\LoggerAwareInterface` with `Psr\Log\LoggerAwareTrait` to inject a logger via setter into any class, without coupling it to a specific implementation.

```php
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait};

class PaymentService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function charge(int $amount): void
    {
        $this->logger?->info('Charging {amount} cents.', ['amount' => $amount]);
    }
}

$service = new PaymentService();
$service->setLogger($logger); // injects any PSR-3 LoggerInterface
$service->charge(4900);
```

`$this->logger` is provided by the trait as `?LoggerInterface`, initialized to `null`. Use the [null-safe operator](https://www.php.net/manual/en/language.oop5.nullsafe.php) (`?->`) to guard calls when no logger has been injected.

