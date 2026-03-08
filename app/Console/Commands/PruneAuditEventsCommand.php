<?php

namespace App\Console\Commands;

use App\Modules\AuditCompliance\Application\Contracts\AuditEventRepository;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** @psalm-suppress PropertyNotSetInConstructor */
final class PruneAuditEventsCommand extends Command
{
    protected $signature = 'audit:prune {--days= : Override the configured retention period in days}';

    protected $description = 'Prune audit events older than the configured or requested retention window.';

    public function handle(AuditEventRepository $auditEventRepository): int
    {
        $daysOption = $this->option('days');
        /** @psalm-suppress MixedAssignment */
        $configuredDays = config('medflow.audit.retention_days', 0);
        $days = is_numeric($daysOption)
            ? (int) $daysOption
            : (is_numeric($configuredDays) ? (int) $configuredDays : 0);

        if ($days <= 0) {
            $this->info('Audit retention pruning skipped because retention is disabled.');

            return self::SUCCESS;
        }

        $deletedCount = $auditEventRepository->pruneOlderThan(CarbonImmutable::now()->subDays($days));

        $this->info("Pruned {$deletedCount} audit events.");

        return self::SUCCESS;
    }
}
