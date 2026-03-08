<?php

namespace App\Modules\IdentityAccess\Presentation\Http\Controllers;

use App\Modules\IdentityAccess\Application\Commands\CreateRoleCommand;
use App\Modules\IdentityAccess\Application\Commands\DeleteRoleCommand;
use App\Modules\IdentityAccess\Application\Commands\SetRolePermissionsCommand;
use App\Modules\IdentityAccess\Application\Commands\UpdateRoleCommand;
use App\Modules\IdentityAccess\Application\Handlers\CreateRoleCommandHandler;
use App\Modules\IdentityAccess\Application\Handlers\DeleteRoleCommandHandler;
use App\Modules\IdentityAccess\Application\Handlers\GetRoleQueryHandler;
use App\Modules\IdentityAccess\Application\Handlers\ListRolePermissionsQueryHandler;
use App\Modules\IdentityAccess\Application\Handlers\ListRolesQueryHandler;
use App\Modules\IdentityAccess\Application\Handlers\SetRolePermissionsCommandHandler;
use App\Modules\IdentityAccess\Application\Handlers\UpdateRoleCommandHandler;
use App\Modules\IdentityAccess\Application\Queries\GetRoleQuery;
use App\Modules\IdentityAccess\Application\Queries\ListRolePermissionsQuery;
use App\Modules\IdentityAccess\Application\Queries\ListRolesQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RoleController
{
    public function list(ListRolesQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($role) => $role->toArray(),
                $handler->handle(new ListRolesQuery),
            ),
        ]);
    }

    public function create(Request $request, CreateRoleCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $handler->handle(new CreateRoleCommand(
            name: $this->validatedString($validated, 'name'),
            description: $this->nullableString($validated, 'description'),
        ));

        return response()->json([
            'status' => 'role_created',
            'data' => $result->toArray(),
        ], 201);
    }

    public function show(string $roleId, GetRoleQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetRoleQuery($roleId))->toArray(),
        ]);
    }

    public function update(string $roleId, Request $request, UpdateRoleCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'filled', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $result = $handler->handle(new UpdateRoleCommand(
            roleId: $roleId,
            name: $this->optionalString($validated, 'name'),
            descriptionProvided: array_key_exists('description', $validated),
            description: $this->nullableString($validated, 'description'),
        ));

        return response()->json([
            'status' => 'role_updated',
            'data' => $result->toArray(),
        ]);
    }

    public function delete(string $roleId, DeleteRoleCommandHandler $handler): JsonResponse
    {
        $handler->handle(new DeleteRoleCommand($roleId));

        return response()->json([
            'status' => 'role_deleted',
        ]);
    }

    public function permissions(string $roleId, ListRolePermissionsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($permission) => $permission->toArray(),
                $handler->handle(new ListRolePermissionsQuery($roleId)),
            ),
        ]);
    }

    public function setPermissions(string $roleId, Request $request, SetRolePermissionsCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['required', 'string', 'max:120'],
        ]);

        $result = $handler->handle(new SetRolePermissionsCommand(
            roleId: $roleId,
            permissionNames: $this->stringList($validated, 'permissions'),
        ));

        return response()->json([
            'status' => 'role_permissions_updated',
            'data' => array_map(
                static fn ($permission) => $permission->toArray(),
                $result,
            ),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function validatedString(array $validated, string $key): string
    {
        /** @var mixed $value */
        $value = $validated[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function nullableString(array $validated, string $key): ?string
    {
        /** @var mixed $value */
        $value = $validated[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function optionalString(array $validated, string $key): ?string
    {
        if (! array_key_exists($key, $validated)) {
            return null;
        }

        return $this->nullableString($validated, $key);
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
