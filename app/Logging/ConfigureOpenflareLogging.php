<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Handler\FilterHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

final class ConfigureOpenflareLogging
{
    public function __invoke(Logger $logger): void
    {
        $level = Logger::toMonologLevel(env('LOG_LEVEL', 'debug'));

        $stdoutHandler = new StreamHandler('php://stdout', $level);
        $stderrHandler = new StreamHandler('php://stderr', max($level, Logger::ERROR));

        $logger->setHandlers([
            new FilterHandler($stdoutHandler, $level, Logger::WARNING),
            $stderrHandler,
        ]);
    }
}
