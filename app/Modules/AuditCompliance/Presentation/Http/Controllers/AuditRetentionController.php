<?php

namespace App\Modules\AuditCompliance\Presentation\Http\Controllers;

use App\Modules\AuditCompliance\Application\Commands\UpdateAuditRetentionCommand;
use App\Modules\AuditCompliance\Application\Handlers\GetAuditRetentionQueryHandler;
use App\Modules\AuditCompliance\Application\Handlers\UpdateAuditRetentionCommandHandler;
use App\Modules\AuditCompliance\Application\Queries\GetAuditRetentionQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuditRetentionController
{
    public function show(GetAuditRetentionQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetAuditRetentionQuery)->toArray(),
        ]);
    }

    public function update(Request $request, UpdateAuditRetentionCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'retention_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ]);
        $retentionDays = is_numeric($validated['retention_days'] ?? null)
            ? (int) $validated['retention_days']
            : 0;

        return response()->json([
            'status' => 'audit_retention_updated',
            'data' => $handler->handle(new UpdateAuditRetentionCommand($retentionDays))->toArray(),
        ]);
    }
}
