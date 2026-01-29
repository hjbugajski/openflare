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
        $stdoutHandler = new StreamHandler('php://stdout', Logger::DEBUG);
        $stderrHandler = new StreamHandler('php://stderr', Logger::ERROR);

        $logger->setHandlers([
            new FilterHandler($stdoutHandler, Logger::DEBUG, Logger::WARNING),
            $stderrHandler,
        ]);
    }
}
