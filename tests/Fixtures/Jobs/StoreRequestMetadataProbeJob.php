<?php

namespace Tests\Fixtures\Jobs;

use App\Shared\Application\Contracts\RequestMetadataContext;
use App\Shared\Application\Contracts\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

final class StoreRequestMetadataProbeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $probeId,
    ) {}

    public function handle(
        RequestMetadataContext $requestMetadataContext,
        TenantContext $tenantContext,
    ): void {
        $metadata = $requestMetadataContext->current();

        DB::table('request_metadata_probes')->insert([
            'probe_id' => $this->probeId,
            'request_id' => $metadata->requestId,
            'correlation_id' => $metadata->correlationId,
            'causation_id' => $metadata->causationId,
            'tenant_id' => $tenantContext->tenantId(),
        ]);
    }
}
