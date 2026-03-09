<?php

namespace App\Modules\TenantManagement\Presentation\Http\Controllers;

use App\Modules\TenantManagement\Application\Commands\CreateDepartmentCommand;
use App\Modules\TenantManagement\Application\Commands\DeleteDepartmentCommand;
use App\Modules\TenantManagement\Application\Commands\UpdateDepartmentCommand;
use App\Modules\TenantManagement\Application\Handlers\CreateDepartmentCommandHandler;
use App\Modules\TenantManagement\Application\Handlers\DeleteDepartmentCommandHandler;
use App\Modules\TenantManagement\Application\Handlers\GetDepartmentQueryHandler;
use App\Modules\TenantManagement\Application\Handlers\ListDepartmentsQueryHandler;
use App\Modules\TenantManagement\Application\Handlers\UpdateDepartmentCommandHandler;
use App\Modules\TenantManagement\Application\Queries\GetDepartmentQuery;
use App\Modules\TenantManagement\Application\Queries\ListDepartmentsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class DepartmentController
{
    public function create(string $clinicId, Request $request, CreateDepartmentCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'phone_extension' => ['nullable', 'string', 'max:16'],
        ]);
        /** @var array<string, mixed> $validated */
        $department = $handler->handle(new CreateDepartmentCommand($clinicId, $validated));

        return response()->json([
            'status' => 'department_created',
            'data' => $department->toArray(),
        ], 201);
    }

    public function delete(string $clinicId, string $departmentId, DeleteDepartmentCommandHandler $handler): JsonResponse
    {
        $department = $handler->handle(new DeleteDepartmentCommand($clinicId, $departmentId));

        return response()->json([
            'status' => 'department_deleted',
            'data' => $department->toArray(),
        ]);
    }

    public function list(string $clinicId, ListDepartmentsQueryHandler $handler): JsonResponse
    {
        $items = [];

        foreach ($handler->handle(new ListDepartmentsQuery($clinicId)) as $department) {
            $items[] = $department->toArray();
        }

        return response()->json([
            'data' => $items,
        ]);
    }

    public function show(string $clinicId, string $departmentId, GetDepartmentQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetDepartmentQuery($clinicId, $departmentId))->toArray(),
        ]);
    }

    public function update(
        string $clinicId,
        string $departmentId,
        Request $request,
        UpdateDepartmentCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'code' => ['sometimes', 'filled', 'string', 'max:32'],
            'name' => ['sometimes', 'filled', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'phone_extension' => ['sometimes', 'nullable', 'string', 'max:16'],
        ]);
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);

        $department = $handler->handle(new UpdateDepartmentCommand($clinicId, $departmentId, $validated));

        return response()->json([
            'status' => 'department_updated',
            'data' => $department->toArray(),
        ]);
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
}
