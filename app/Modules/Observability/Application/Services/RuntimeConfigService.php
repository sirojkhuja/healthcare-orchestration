<?php

namespace App\Modules\Observability\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditEventRepository;
use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Observability\Application\Data\RuntimeConfigData;
use App\Shared\Application\Contracts\TenantCache;
use App\Shared\Application\Contracts\TenantContext;

final class RuntimeConfigService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantCache $tenantCache,
        private readonly AuditEventRepository $auditEventRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function get(): RuntimeConfigData
    {
        $tenantId = $this->tenantContext->requireTenantId();

        /** @var RuntimeConfigData $config */
        $config = $this->tenantCache->remember(
            'ops',
            ['runtime-config'],
            $tenantId,
            300,
            fn (): RuntimeConfigData => $this->build(),
        );

        return $config;
    }

    public function reload(): RuntimeConfigData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $this->tenantCache->invalidate('ops', $tenantId);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'admin.runtime_config_reloaded',
            objectType: 'runtime_config',
            objectId: 'runtime',
            after: ['reloaded' => true],
        ));

        return $this->build();
    }

    private function build(): RuntimeConfigData
    {
        $events = $this->auditEventRepository->forActionPrefix(
            'admin.runtime_config_reloaded',
            $this->tenantContext->requireTenantId(),
            1,
        );
        $lastReloadedAt = $events !== [] ? $events[0]->occurredAt : null;
        $brokers = $this->brokers();

        return new RuntimeConfigData(
            service: config()->string('app.name'),
            environment: config()->string('app.env'),
            version: config()->string('medflow.version'),
            cacheStore: config()->string('cache.default'),
            queueConnection: config()->string('queue.default'),
            modules: $this->modules(),
            brokers: $brokers,
            consumerGroup: config()->string('medflow.kafka.group_id'),
            outboxBatchSize: config()->integer('medflow.kafka.outbox.batch_size'),
            outboxMaxAttempts: config()->integer('medflow.kafka.outbox.max_attempts'),
            lastReloadedAt: $lastReloadedAt,
        );
    }

    /**
     * @return list<string>
     */
    private function brokers(): array
    {
        $brokers = trim(config()->string('medflow.kafka.brokers', ''));

        if ($brokers === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $brokers))));
    }

    /**
     * @return list<string>
     */
    private function modules(): array
    {
        $configured = config('medflow.modules', []);

        if (! is_array($configured)) {
            return [];
        }

        $modules = array_values(array_filter(
            $configured,
            static fn (mixed $module): bool => is_string($module),
        ));

        /** @var list<string> $modules */

        return $modules;
    }
}
