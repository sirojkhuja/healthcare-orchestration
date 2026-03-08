<?php

namespace App\Modules\IdentityAccess\Presentation\Http\Controllers;

use App\Modules\IdentityAccess\Application\Handlers\GetRbacAuditQueryHandler;
use App\Modules\IdentityAccess\Application\Queries\GetRbacAuditQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RbacAuditController
{
    public function list(Request $request, GetRbacAuditQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = array_key_exists('limit', $validated) && is_numeric($validated['limit'])
            ? (int) $validated['limit']
            : 50;

        return response()->json([
            'data' => array_map(
                static fn ($event) => $event->toArray(),
                $handler->handle(new GetRbacAuditQuery($limit)),
            ),
        ]);
    }
}
