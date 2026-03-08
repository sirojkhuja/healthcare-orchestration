<?php

namespace App\Modules\TenantManagement\Presentation\Http\Controllers;

use App\Modules\TenantManagement\Application\Commands\CreateTenantCommand;
use App\Modules\TenantManagement\Application\Commands\DeleteTenantCommand;
use App\Modules\TenantManagement\Application\Commands\UpdateTenantCommand;
use App\Modules\TenantManagement\Application\Data\TenantData;
use App\Modules\TenantManagement\Application\Handlers\CreateTenantCommandHandler;
use App\Modules\TenantManagement\Application\Handlers\DeleteTenantCommandHandler;
use App\Modules\TenantManagement\Application\Handlers\GetTenantQueryHandler;
use App\Modules\TenantManagement\Application\Handlers\ListTenantsQueryHandler;
use App\Modules\TenantManagement\Application\Handlers\UpdateTenantCommandHandler;
use App\Modules\TenantManagement\Application\Queries\GetTenantQuery;
use App\Modules\TenantManagement\Application\Queries\ListTenantsQuery;
use App\Modules\TenantManagement\Domain\Tenants\TenantStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class TenantController
{
    public function list(Request $request, ListTenantsQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:'.implode(',', TenantStatus::all())],
        ]);

        return response()->json([
            'data' => array_map(
                static fn (TenantData $tenant): array => $tenant->toArray(),
                $handler->handle(new ListTenantsQuery(
                    search: $this->nullableString($validated, 'q'),
                    status: $this->nullableString($validated, 'status'),
                )),
            ),
        ]);
    }

    public function create(Request $request, CreateTenantCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'contact_email' => ['nullable', 'email:rfc,dns', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
        ]);

        $tenant = $handler->handle(new CreateTenantCommand(
            name: $this->validatedString($validated, 'name'),
            contactEmail: $this->nullableString($validated, 'contact_email'),
            contactPhone: $this->nullableString($validated, 'contact_phone'),
        ));

        return response()->json([
            'status' => 'tenant_created',
            'data' => $tenant->toArray(),
        ], 201);
    }

    public function show(string $tenantId, GetTenantQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetTenantQuery($tenantId))->toArray(),
        ]);
    }

    public function update(string $tenantId, Request $request, UpdateTenantCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'filled', 'string', 'max:160'],
            'contact_email' => ['sometimes', 'nullable', 'email:rfc,dns', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:32'],
        ]);

        $this->assertNonEmptyPatch($validated);

        $tenant = $handler->handle(new UpdateTenantCommand(
            tenantId: $tenantId,
            nameProvided: array_key_exists('name', $validated),
            name: $this->nullableString($validated, 'name'),
            contactEmailProvided: array_key_exists('contact_email', $validated),
            contactEmail: $this->nullableString($validated, 'contact_email'),
            contactPhoneProvided: array_key_exists('contact_phone', $validated),
            contactPhone: $this->nullableString($validated, 'contact_phone'),
        ));

        return response()->json([
            'status' => 'tenant_updated',
            'data' => $tenant->toArray(),
        ]);
    }

    public function delete(string $tenantId, DeleteTenantCommandHandler $handler): JsonResponse
    {
        $tenant = $handler->handle(new DeleteTenantCommand($tenantId));

        return response()->json([
            'status' => 'tenant_deleted',
            'data' => $tenant->toArray(),
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
    private function validatedString(array $validated, string $key): string
    {
        /** @var mixed $value */
        $value = $validated[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}
