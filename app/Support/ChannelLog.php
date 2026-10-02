<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Base for per-feature loggers that write to their own file instead of laravel.log.
 *
 * Subclasses only declare a CHANNEL matching a channel in config/logging.php. Call
 * sites then use the subclass exactly like the Log facade — controllers alias it on
 * import (`use App\Support\XyzLog as Log;`), so existing Log::info()/warning()/error()
 * calls land in the dedicated file with no other edits.
 *
 * Every entry is stamped with who did it and which request it came from, which is
 * what makes a per-feature log readable when several users are working at once.
 *
 * @method static void emergency(string $message, array $context = [])
 * @method static void alert(string $message, array $context = [])
 * @method static void critical(string $message, array $context = [])
 * @method static void error(string $message, array $context = [])
 * @method static void warning(string $message, array $context = [])
 * @method static void notice(string $message, array $context = [])
 * @method static void info(string $message, array $context = [])
 * @method static void debug(string $message, array $context = [])
 * @method static void log(string $level, string $message, array $context = [])
 */
abstract class ChannelLog
{
    /** Channel name as defined in config/logging.php. */
    public const CHANNEL = 'stack';

    private const LEVELS = [
        'emergency', 'alert', 'critical', 'error',
        'warning', 'notice', 'info', 'debug', 'log',
    ];

    /**
     * Forward any Log facade method to the right place.
     *
     * A level call (info, warning, error, debug, log, ...) goes to the dedicated
     * channel with the actor/request stamp merged into its context. Everything else
     * goes to the LogManager untouched.
     *
     * That split matters because this class is aliased over the Log facade
     * (`use App\Support\XyzLog as Log;`), which is a promise that every existing
     * Log:: call in the file keeps working. LogManager methods — build(), stack(),
     * driver(), extend() — do not exist on the Logger that Log::channel() returns, so
     * forwarding them there turns a working line into a fatal
     * "Call to undefined method Monolog\Logger::build()" at runtime, in whatever rare
     * branch happens to use one. Production found exactly that in a try/catch'd audit
     * writer, where it degraded silently into a warning.
     */
    public static function __callStatic(string $method, array $arguments)
    {
        if (! \in_array($method, self::LEVELS, true)) {
            return Log::{$method}(...$arguments);
        }

        // Log::log($level, $message, $context) puts the context third; every other
        // level helper puts it second.
        $contextIndex = $method === 'log' ? 2 : 1;

        $context = $arguments[$contextIndex] ?? [];
        $arguments[$contextIndex] = \is_array($context)
            ? \array_merge(self::stamp(), $context)
            : $context;

        return Log::channel(static::CHANNEL)->{$method}(...$arguments);
    }

    /**
     * The underlying channel logger, for callers that want it directly.
     *
     * The optional $channel exists so that aliasing this class over the Log facade
     * (`use App\Support\XyzLog as Log;`) stays honest. A controller that already
     * routes some of its entries elsewhere — Log::channel('mls_batch'), say — would
     * otherwise have that name silently ignored by a no-argument override and its
     * entries diverted into the feature channel, which is the one failure a logging
     * change must never introduce. Called with no argument it still returns the
     * feature's own channel.
     */
    public static function channel(?string $channel = null)
    {
        return Log::channel($channel ?? static::CHANNEL);
    }

    /**
     * Actor / request identity attached to every entry.
     */
    private static function stamp(): array
    {
        $request = request();

        return [
            'user_id' => Auth::id(),
            'ip' => $request ? $request->ip() : null,
            'route' => $request ? $request->path() : null,
        ];
    }
}
