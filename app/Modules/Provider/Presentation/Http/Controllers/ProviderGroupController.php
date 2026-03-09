<?php

namespace App\Modules\Provider\Presentation\Http\Controllers;

use App\Modules\Provider\Application\Commands\CreateProviderGroupCommand;
use App\Modules\Provider\Application\Commands\SetProviderGroupMembersCommand;
use App\Modules\Provider\Application\Handlers\CreateProviderGroupCommandHandler;
use App\Modules\Provider\Application\Handlers\ListProviderGroupsQueryHandler;
use App\Modules\Provider\Application\Handlers\SetProviderGroupMembersCommandHandler;
use App\Modules\Provider\Application\Queries\ListProviderGroupsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProviderGroupController
{
    public function create(Request $request, CreateProviderGroupCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'clinic_id' => ['nullable', 'uuid'],
        ]);
        /** @var array<string, mixed> $validated */
        $group = $handler->handle(new CreateProviderGroupCommand($validated));

        return response()->json([
            'status' => 'provider_group_created',
            'data' => $group->toArray(),
        ], 201);
    }

    public function list(ListProviderGroupsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($group): array => $group->toArray(),
                $handler->handle(new ListProviderGroupsQuery),
            ),
        ]);
    }

    public function updateMembers(
        string $groupId,
        Request $request,
        SetProviderGroupMembersCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'provider_ids' => ['required', 'array', 'max:200'],
            'provider_ids.*' => ['uuid', 'distinct'],
        ]);
        /** @var array{provider_ids: list<string>} $validated */
        $group = $handler->handle(new SetProviderGroupMembersCommand($groupId, $validated['provider_ids']));

        return response()->json([
            'status' => 'provider_group_members_updated',
            'data' => $group->toArray(),
        ]);
    }
}
