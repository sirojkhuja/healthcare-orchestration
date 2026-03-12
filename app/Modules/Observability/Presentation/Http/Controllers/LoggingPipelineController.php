<?php

namespace App\Modules\Observability\Presentation\Http\Controllers;

use App\Modules\Observability\Application\Commands\ReloadLoggingPipelinesCommand;
use App\Modules\Observability\Application\Data\LoggingPipelineData;
use App\Modules\Observability\Application\Handlers\ListLoggingPipelinesQueryHandler;
use App\Modules\Observability\Application\Handlers\ReloadLoggingPipelinesCommandHandler;
use App\Modules\Observability\Application\Queries\ListLoggingPipelinesQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LoggingPipelineController
{
    public function list(ListLoggingPipelinesQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn (LoggingPipelineData $pipeline): array => $pipeline->toArray(),
                $handler->handle(new ListLoggingPipelinesQuery),
            ),
        ]);
    }

    public function reload(Request $request, ReloadLoggingPipelinesCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'pipelines' => ['nullable', 'array'],
            'pipelines.*' => ['string', 'max:64'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'status' => 'logging_pipelines_reloaded',
            'data' => array_map(
                static fn (LoggingPipelineData $pipeline): array => $pipeline->toArray(),
                $handler->handle(new ReloadLoggingPipelinesCommand($this->stringArray($validated, 'pipelines'))),
            ),
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
