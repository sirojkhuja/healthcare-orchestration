<?php

namespace App\Modules\Observability\Presentation\Http\Controllers;

use App\Modules\Observability\Application\Commands\FlushCacheCommand;
use App\Modules\Observability\Application\Commands\RebuildCachesCommand;
use App\Modules\Observability\Application\Handlers\FlushCacheCommandHandler;
use App\Modules\Observability\Application\Handlers\RebuildCachesCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CacheAdminController
{
    public function flush(Request $request, FlushCacheCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'domains' => ['nullable', 'array'],
            'domains.*' => ['string', 'max:64'],
            'include_global_reference_data' => ['nullable', 'boolean'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'status' => 'cache_flushed',
            'data' => $handler->handle(new FlushCacheCommand(
                domains: $this->stringArray($validated, 'domains'),
                includeGlobalReferenceData: (bool) ($validated['include_global_reference_data'] ?? false),
            ))->toArray(),
        ]);
    }

    public function rebuild(Request $request, RebuildCachesCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'domains' => ['nullable', 'array'],
            'domains.*' => ['string', 'max:64'],
            'include_global_reference_data' => ['nullable', 'boolean'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'status' => 'caches_rebuilt',
            'data' => $handler->handle(new RebuildCachesCommand(
                domains: $this->stringArray($validated, 'domains'),
                includeGlobalReferenceData: (bool) ($validated['include_global_reference_data'] ?? false),
            ))->toArray(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<string>|null
     */
    private function stringArray(array $validated, string $key): ?array
    {
        if (! array_key_exists($key, $validated) || ! is_array($validated[$key])) {
            return null;
        }

        return array_values(array_filter($validated[$key], 'is_string'));
    }
}
