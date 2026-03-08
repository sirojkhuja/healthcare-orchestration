<?php

namespace App\Modules\IdentityAccess\Presentation\Http\Controllers;

use App\Modules\IdentityAccess\Application\Commands\RevokeAllSessionsCommand;
use App\Modules\IdentityAccess\Application\Commands\RevokeSessionCommand;
use App\Modules\IdentityAccess\Application\Handlers\ListSessionsQueryHandler;
use App\Modules\IdentityAccess\Application\Handlers\RevokeAllSessionsCommandHandler;
use App\Modules\IdentityAccess\Application\Handlers\RevokeSessionCommandHandler;
use App\Modules\IdentityAccess\Application\Queries\ListSessionsQuery;
use Illuminate\Http\JsonResponse;

final class SecurityController
{
    public function listSessions(ListSessionsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($session) => $session->toArray(),
                $handler->handle(new ListSessionsQuery),
            ),
        ]);
    }

    public function revokeSession(string $sessionId, RevokeSessionCommandHandler $handler): JsonResponse
    {
        $handler->handle(new RevokeSessionCommand($sessionId));

        return response()->json([
            'status' => 'session_revoked',
        ]);
    }

    public function revokeAllSessions(RevokeAllSessionsCommandHandler $handler): JsonResponse
    {
        return response()->json([
            'status' => 'all_sessions_revoked',
            'revoked_sessions' => $handler->handle(new RevokeAllSessionsCommand),
        ]);
    }
}
