<?php

namespace App\Modules\Observability\Presentation\Http\Controllers;

use App\Modules\Observability\Application\Commands\RetryJobCommand;
use App\Modules\Observability\Application\Data\JobSearchCriteria;
use App\Modules\Observability\Application\Handlers\ListJobsQueryHandler;
use App\Modules\Observability\Application\Handlers\RetryJobCommandHandler;
use App\Modules\Observability\Application\Queries\ListJobsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class JobAdminController
{
    public function list(Request $request, ListJobsQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'queue' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'data' => $handler->handle(new ListJobsQuery(
                new JobSearchCriteria(
                    queue: $this->nullableString($validated, 'queue'),
                    limit: is_numeric($validated['limit'] ?? null) ? (int) $validated['limit'] : 50,
                ),
            ))->toArray(),
        ]);
    }

    public function retry(string $jobId, RetryJobCommandHandler $handler): JsonResponse
    {
        return response()->json([
            'status' => 'job_retried',
            'data' => $handler->handle(new RetryJobCommand($jobId))->toArray(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function nullableString(array $validated, string $key): ?string
    {
        return array_key_exists($key, $validated) && is_string($validated[$key]) && trim($validated[$key]) !== ''
            ? trim($validated[$key])
            : null;
    }
}
