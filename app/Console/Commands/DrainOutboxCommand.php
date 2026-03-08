<?php

namespace App\Console\Commands;

use App\Shared\Application\Services\OutboxRelay;
use Illuminate\Console\Command;

/** @psalm-suppress PropertyNotSetInConstructor */
final class DrainOutboxCommand extends Command
{
    protected $signature = 'outbox:drain {--limit=50 : Maximum number of outbox messages to publish in one pass}';

    protected $description = 'Publish ready outbox messages to Kafka and schedule retries for failures.';

    public function handle(OutboxRelay $outboxRelay): int
    {
        /** @psalm-suppress MixedAssignment */
        $limitOption = $this->option('limit');
        $limit = is_numeric($limitOption) && (int) $limitOption > 0 ? (int) $limitOption : 50;
        $result = $outboxRelay->drain($limit);

        $this->info("Claimed {$result->claimed} outbox messages.");
        $this->info("Delivered {$result->delivered} outbox messages.");
        $this->info("Failed {$result->failed} outbox messages.");

        return self::SUCCESS;
    }
}
