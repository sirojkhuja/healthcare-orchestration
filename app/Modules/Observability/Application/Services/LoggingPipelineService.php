<?php

namespace App\Modules\Observability\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditEventRepository;
use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Observability\Application\Data\LoggingPipelineData;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class LoggingPipelineService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditEventRepository $auditEventRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @return list<LoggingPipelineData>
     */
    public function list(): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $reloadEvents = $this->auditEventRepository->forActionPrefix('admin.logging_pipelines_reloaded', $tenantId, 100);
        $lastReloadedAt = [];

        foreach ($reloadEvents as $event) {
            if (array_key_exists($event->objectId, $lastReloadedAt)) {
                continue;
            }

            $lastReloadedAt[$event->objectId] = $event->occurredAt;
        }

        $pipelines = [];

        foreach ($this->catalog() as $key => $pipeline) {
            $enabled = $pipeline['enabled'] ?? false;
            $pipelines[] = new LoggingPipelineData(
                key: $key,
                name: $pipeline['name'] ?? $key,
                destination: $pipeline['destination'] ?? 'unknown',
                enabled: $enabled,
                status: $enabled ? 'active' : 'disabled',
                lastReloadedAt: $lastReloadedAt[$key] ?? null,
            );
        }

        usort($pipelines, static fn (LoggingPipelineData $left, LoggingPipelineData $right): int => $left->key <=> $right->key);

        return $pipelines;
    }

    /**
     * @param  list<string>|null  $pipelines
     * @return list<LoggingPipelineData>
     */
    public function reload(?array $pipelines): array
    {
        $catalog = $this->catalog();
        $selected = $pipelines === null || $pipelines === [] ? array_keys($catalog) : array_values(array_unique($pipelines));

        foreach ($selected as $pipelineKey) {
            if (! array_key_exists($pipelineKey, $catalog)) {
                throw new UnprocessableEntityHttpException('The logging pipeline key is not supported.');
            }

            $this->auditTrailWriter->record(new AuditRecordInput(
                action: 'admin.logging_pipelines_reloaded',
                objectType: 'logging_pipeline',
                objectId: $pipelineKey,
                after: [
                    'pipeline' => $pipelineKey,
                    'reloaded' => true,
                ],
            ));
        }

        return $this->list();
    }

    /**
     * @return array<string, array{name?: string, destination?: string, enabled?: bool}>
     */
    private function catalog(): array
    {
        $configured = config('operations.logging_pipelines', []);

        if (! is_array($configured)) {
            return [];
        }

        $catalog = [];

        foreach ($configured as $key => $pipeline) {
            if (! is_string($key) || ! is_array($pipeline)) {
                continue;
            }

            $name = $pipeline['name'] ?? null;
            $destination = $pipeline['destination'] ?? null;
            $enabled = $pipeline['enabled'] ?? null;

            if (! is_string($name) || ! is_string($destination) || ! is_bool($enabled)) {
                continue;
            }

            $catalog[$key] = [
                'name' => $name,
                'destination' => $destination,
                'enabled' => $enabled,
            ];
        }

        return $catalog;
    }
}
