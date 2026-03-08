<?php

namespace App\Modules\IdentityAccess\Presentation\Http\Controllers;

use App\Modules\IdentityAccess\Application\Commands\SetUserRolesCommand;
use App\Modules\IdentityAccess\Application\Handlers\GetUserPermissionsQueryHandler;
use App\Modules\IdentityAccess\Application\Handlers\ListUserRolesQueryHandler;
use App\Modules\IdentityAccess\Application\Handlers\SetUserRolesCommandHandler;
use App\Modules\IdentityAccess\Application\Queries\GetUserPermissionsQuery;
use App\Modules\IdentityAccess\Application\Queries\ListUserRolesQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserRoleController
{
    public function list(string $userId, ListUserRolesQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($role) => $role->toArray(),
                $handler->handle(new ListUserRolesQuery($userId)),
            ),
        ]);
    }

    public function update(string $userId, Request $request, SetUserRolesCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'role_ids' => ['present', 'array'],
            'role_ids.*' => ['required', 'uuid'],
        ]);

        $result = $handler->handle(new SetUserRolesCommand(
            userId: $userId,
            roleIds: $this->stringList($validated, 'role_ids'),
        ));

        return response()->json([
            'status' => 'user_roles_updated',
            'data' => array_map(
                static fn ($role) => $role->toArray(),
                $result,
            ),
        ]);
    }

    public function permissions(string $userId, GetUserPermissionsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetUserPermissionsQuery($userId))->toArray(),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $validated
     * @return list<string>
     */
    private function stringList(array $validated, string $key): array
    {
        /** @var mixed $value */
        $value = $validated[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn (mixed $item): bool => is_string($item) && $item !== '',
        ));
    }
}
