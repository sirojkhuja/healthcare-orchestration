<?php

namespace App\Modules\IdentityAccess\Presentation\Http\Controllers;

use App\Modules\IdentityAccess\Application\Commands\ActivateUserCommand;
use App\Modules\IdentityAccess\Application\Commands\AdminResetPasswordCommand;
use App\Modules\IdentityAccess\Application\Commands\BulkImportUsersCommand;
use App\Modules\IdentityAccess\Application\Commands\BulkUpdateUsersCommand;
use App\Modules\IdentityAccess\Application\Commands\CreateUserCommand;
use App\Modules\IdentityAccess\Application\Commands\DeactivateUserCommand;
use App\Modules\IdentityAccess\Application\Commands\DeleteUserCommand;
use App\Modules\IdentityAccess\Application\Commands\LockUserCommand;
use App\Modules\IdentityAccess\Application\Commands\UnlockUserCommand;
use App\Modules\IdentityAccess\Application\Commands\UpdateUserCommand;
use App\Modules\IdentityAccess\Application\Data\ManagedUserData;
use App\Modules\IdentityAccess\Application\Handlers\ActivateUserCommandHandler;
use App\Modules\IdentityAccess\Application\Handlers\AdminResetPasswordCommandHandler;
use App\Modules\IdentityAccess\Application\Handlers\BulkImportUsersCommandHandler;
use App\Modules\IdentityAccess\Application\Handlers\BulkUpdateUsersCommandHandler;
use App\Modules\IdentityAccess\Application\Handlers\CreateUserCommandHandler;
use App\Modules\IdentityAccess\Application\Handlers\DeactivateUserCommandHandler;
use App\Modules\IdentityAccess\Application\Handlers\DeleteUserCommandHandler;
use App\Modules\IdentityAccess\Application\Handlers\GetUserQueryHandler;
use App\Modules\IdentityAccess\Application\Handlers\ListUsersQueryHandler;
use App\Modules\IdentityAccess\Application\Handlers\LockUserCommandHandler;
use App\Modules\IdentityAccess\Application\Handlers\UnlockUserCommandHandler;
use App\Modules\IdentityAccess\Application\Handlers\UpdateUserCommandHandler;
use App\Modules\IdentityAccess\Application\Queries\GetUserQuery;
use App\Modules\IdentityAccess\Application\Queries\ListUsersQuery;
use App\Modules\IdentityAccess\Domain\Users\TenantUserStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class UserController
{
    public function list(Request $request, ListUsersQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:'.implode(',', TenantUserStatus::all())],
        ]);

        return response()->json([
            'data' => array_map(
                static fn (ManagedUserData $user): array => $user->toArray(),
                $handler->handle(new ListUsersQuery(
                    search: $this->nullableString($validated, 'q'),
                    status: $this->nullableString($validated, 'status'),
                )),
            ),
        ]);
    }

    public function create(Request $request, CreateUserCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc,dns', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'status' => ['nullable', 'string', 'in:'.implode(',', TenantUserStatus::creatable())],
        ]);

        $result = $handler->handle(new CreateUserCommand(
            name: $this->validatedString($validated, 'name'),
            email: $this->validatedString($validated, 'email'),
            password: $this->nullableString($validated, 'password'),
            status: $this->nullableString($validated, 'status'),
        ));

        return response()->json([
            'status' => 'user_created',
            'data' => $result->toArray(),
        ], 201);
    }

    public function show(string $userId, GetUserQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetUserQuery($userId))->toArray(),
        ]);
    }

    public function update(string $userId, Request $request, UpdateUserCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'filled', 'string', 'max:120'],
            'email' => ['sometimes', 'email:rfc,dns', 'max:255'],
        ]);

        $this->assertNonEmptyPatch($validated);

        $result = $handler->handle(new UpdateUserCommand(
            userId: $userId,
            name: $this->optionalString($validated, 'name'),
            email: $this->optionalString($validated, 'email'),
        ));

        return response()->json([
            'status' => 'user_updated',
            'data' => $result->toArray(),
        ]);
    }

    public function delete(string $userId, DeleteUserCommandHandler $handler): JsonResponse
    {
        $result = $handler->handle(new DeleteUserCommand($userId));

        return response()->json([
            'status' => 'user_deleted',
            'data' => $result->toArray(),
        ]);
    }

    public function activate(string $userId, ActivateUserCommandHandler $handler): JsonResponse
    {
        return $this->statusResponse('user_activated', $handler->handle(new ActivateUserCommand($userId)));
    }

    public function deactivate(string $userId, DeactivateUserCommandHandler $handler): JsonResponse
    {
        return $this->statusResponse('user_deactivated', $handler->handle(new DeactivateUserCommand($userId)));
    }

    public function lock(string $userId, LockUserCommandHandler $handler): JsonResponse
    {
        return $this->statusResponse('user_locked', $handler->handle(new LockUserCommand($userId)));
    }

    public function unlock(string $userId, UnlockUserCommandHandler $handler): JsonResponse
    {
        return $this->statusResponse('user_unlocked', $handler->handle(new UnlockUserCommand($userId)));
    }

    public function resetPassword(string $userId, Request $request, AdminResetPasswordCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $result = $handler->handle(new AdminResetPasswordCommand(
            userId: $userId,
            password: $this->validatedString($validated, 'password'),
        ));

        return response()->json([
            'status' => 'user_password_reset',
            'revoked_sessions' => $result['revoked_sessions'],
            'data' => $result['user']->toArray(),
        ]);
    }

    public function bulkImport(Request $request, BulkImportUsersCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'users' => ['required', 'array', 'min:1', 'max:100'],
            'users.*.name' => ['required', 'string', 'max:120'],
            'users.*.email' => ['required', 'email:rfc,dns', 'max:255'],
            'users.*.password' => ['nullable', 'string', 'min:8'],
            'users.*.status' => ['nullable', 'string', 'in:'.implode(',', TenantUserStatus::creatable())],
        ]);

        $result = $handler->handle(new BulkImportUsersCommand($this->importUsers($validated)));

        return response()->json([
            'status' => 'users_imported',
            'data' => $result->toArray(),
        ]);
    }

    public function bulkUpdate(Request $request, BulkUpdateUsersCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:activate,deactivate,lock,unlock,delete'],
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['required', 'uuid', 'distinct'],
        ]);

        $result = $handler->handle(new BulkUpdateUsersCommand(
            action: $this->validatedString($validated, 'action'),
            userIds: $this->stringList($validated, 'user_ids'),
        ));

        return response()->json([
            'status' => 'users_bulk_updated',
            'data' => $result->toArray(),
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
        return array_key_exists($key, $validated) ? $this->nullableString($validated, $key) : null;
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function assertNonEmptyPatch(array $validated): void
    {
        if ($validated === []) {
            throw ValidationException::withMessages([
                'payload' => ['At least one updatable field is required.'],
            ]);
        }
    }

    /**
     * @param  array<array-key, mixed>  $validated
     * @return list<array{name: string, email: string, password?: string|null, status?: string|null}>
     */
    private function importUsers(array $validated): array
    {
        /** @var mixed $users */
        $users = $validated['users'] ?? [];

        if (! is_array($users)) {
            return [];
        }

        $importUsers = [];

        foreach (array_values($users) as $user) {
            if (! is_array($user)) {
                continue;
            }

            $importUsers[] = [
                'name' => $this->validatedString($user, 'name'),
                'email' => $this->validatedString($user, 'email'),
                'password' => $this->nullableString($user, 'password'),
                'status' => $this->nullableString($user, 'status'),
            ];
        }

        return $importUsers;
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

    private function statusResponse(string $status, ManagedUserData $user): JsonResponse
    {
        return response()->json([
            'status' => $status,
            'data' => $user->toArray(),
        ]);
    }
}
