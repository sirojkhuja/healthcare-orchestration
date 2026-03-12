<?php

namespace App\Modules\AuditCompliance\Presentation\Http\Controllers;

use App\Modules\AuditCompliance\Application\Data\AuditEventData;
use App\Modules\AuditCompliance\Application\Handlers\GetObjectAuditQueryHandler;
use App\Modules\AuditCompliance\Application\Queries\GetObjectAuditQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ObjectAuditController
{
    public function list(
        string $objectType,
        string $objectId,
        Request $request,
        GetObjectAuditQueryHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'action_prefix' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = array_key_exists('limit', $validated) && is_numeric($validated['limit'])
            ? (int) $validated['limit']
            : 50;

        return response()->json([
            'data' => array_map(
                static fn (AuditEventData $event): array => $event->toArray(),
                $handler->handle(new GetObjectAuditQuery(
                    objectType: $objectType,
                    objectId: $objectId,
                    actionPrefix: array_key_exists('action_prefix', $validated) && is_string($validated['action_prefix']) && $validated['action_prefix'] !== ''
                        ? $validated['action_prefix']
                        : null,
                    limit: $limit,
                )),
            ),
            'meta' => [
                'object_type' => $objectType,
                'object_id' => $objectId,
                'limit' => $limit,
            ],
        ]);
    }
}
