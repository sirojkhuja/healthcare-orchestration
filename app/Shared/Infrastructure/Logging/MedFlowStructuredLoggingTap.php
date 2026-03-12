<?php

namespace App\Shared\Infrastructure\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Logger as MonologLogger;

final class MedFlowStructuredLoggingTap
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if (! $monolog instanceof MonologLogger) {
            return;
        }

        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true);
        $formatter->includeStacktraces(true);

        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof FormattableHandlerInterface) {
                $handler->setFormatter($formatter);
            }
        }

        /** @var ContextEnrichingLogProcessor $processor */
        $processor = app(ContextEnrichingLogProcessor::class);
        $monolog->pushProcessor($processor);
    }
}
