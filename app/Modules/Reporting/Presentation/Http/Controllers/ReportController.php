<?php

namespace App\Modules\Reporting\Presentation\Http\Controllers;

use App\Modules\Reporting\Application\Commands\CreateReportCommand;
use App\Modules\Reporting\Application\Commands\DeleteReportCommand;
use App\Modules\Reporting\Application\Commands\RunReportCommand;
use App\Modules\Reporting\Application\Data\ReportSearchCriteria;
use App\Modules\Reporting\Application\Handlers\CreateReportCommandHandler;
use App\Modules\Reporting\Application\Handlers\DeleteReportCommandHandler;
use App\Modules\Reporting\Application\Handlers\DownloadReportQueryHandler;
use App\Modules\Reporting\Application\Handlers\GetReportQueryHandler;
use App\Modules\Reporting\Application\Handlers\ListReportsQueryHandler;
use App\Modules\Reporting\Application\Handlers\RunReportCommandHandler;
use App\Modules\Reporting\Application\Queries\DownloadReportQuery;
use App\Modules\Reporting\Application\Queries\GetReportQuery;
use App\Modules\Reporting\Application\Queries\ListReportsQuery;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportController
{
    public function create(Request $request, CreateReportCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'source' => ['required', 'string', 'in:patients,providers,appointments,invoices,claims'],
            'format' => ['required', 'string', 'in:csv'],
            'filters' => ['nullable', 'array'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'status' => 'report_created',
            'data' => $handler->handle(new CreateReportCommand($validated))->toArray(),
        ], 201);
    }

    public function delete(string $reportId, DeleteReportCommandHandler $handler): JsonResponse
    {
        return response()->json([
            'status' => 'report_deleted',
            'data' => $handler->handle(new DeleteReportCommand($reportId))->toArray(),
        ]);
    }

    public function download(string $reportId, DownloadReportQueryHandler $handler): StreamedResponse
    {
        $run = $handler->handle(new DownloadReportQuery($reportId));
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($run->storageDisk);

        return $disk->download($run->storagePath, $run->fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function list(Request $request, ListReportsQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'in:patients,providers,appointments,invoices,claims'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        /** @var array<string, mixed> $validated */
        /** @var string|null $query */
        $query = $validated['q'] ?? null;
        /** @var string|null $source */
        $source = $validated['source'] ?? null;
        $criteria = new ReportSearchCriteria(
            query: $query,
            source: $source,
            limit: $this->integerValue($validated['limit'] ?? null, 25),
        );

        return response()->json([
            'data' => array_map(
                static fn ($report): array => $report->toArray(),
                $handler->handle(new ListReportsQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function run(string $reportId, RunReportCommandHandler $handler): JsonResponse
    {
        return response()->json([
            'status' => 'report_run_completed',
            'data' => $handler->handle(new RunReportCommand($reportId))->toArray(),
        ]);
    }

    public function show(string $reportId, GetReportQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetReportQuery($reportId))->toArray(),
        ]);
    }

    private function integerValue(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        return $default;
    }
}
