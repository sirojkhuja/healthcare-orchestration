<?php

namespace App\Modules\AuditCompliance\Presentation\Http\Controllers;

use App\Modules\AuditCompliance\Application\Data\ComplianceReportData;
use App\Modules\AuditCompliance\Application\Data\ComplianceReportSearchCriteria;
use App\Modules\AuditCompliance\Application\Handlers\ListComplianceReportsQueryHandler;
use App\Modules\AuditCompliance\Application\Queries\ListComplianceReportsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ComplianceReportController
{
    public function list(Request $request, ListComplianceReportsQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:pii_key_rotation,pii_reencryption'],
            'status' => ['nullable', 'string', 'in:completed'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $criteria = new ComplianceReportSearchCriteria(
            type: array_key_exists('type', $validated) && is_string($validated['type']) && $validated['type'] !== ''
                ? $validated['type']
                : null,
            status: array_key_exists('status', $validated) && is_string($validated['status']) && $validated['status'] !== ''
                ? $validated['status']
                : null,
            limit: array_key_exists('limit', $validated) && is_numeric($validated['limit'])
                ? (int) $validated['limit']
                : 50,
        );

        return response()->json([
            'data' => array_map(
                static fn (ComplianceReportData $report): array => $report->toArray(),
                $handler->handle(new ListComplianceReportsQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }
}
