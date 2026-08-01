<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\FilterHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;

/**
 * Splits the `openflare` channel across the two streams platforms like Railway
 * classify on: records below ERROR go to stdout, ERROR and above to stderr.
 *
 * `LogManager::tap()` hands over Laravel's logger wrapper, so the Monolog
 * instance has to be unwrapped before its handlers can be replaced.
 */
final class ConfigureOpenflareLogging
{
    public function __invoke(Logger $logger): void
    {
        // Read from config, not env(): env() returns null once config:cache has
        // run against a .env-sourced variable.
        $level = Level::fromName(config('logging.channels.openflare.level') ?? 'debug');
        $stderrLevel = Level::from(max($level->value, Level::Error->value));

        $logger->getLogger()->setHandlers([
            new FilterHandler(new StreamHandler('php://stdout', $level), $level, Level::Warning),
            new StreamHandler('php://stderr', $stderrLevel),
        ]);
    }
}
