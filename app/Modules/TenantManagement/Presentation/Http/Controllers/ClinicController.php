<?php

namespace App\Modules\TenantManagement\Presentation\Http\Controllers;

use App\Modules\TenantManagement\Application\Commands\CreateClinicCommand;
use App\Modules\TenantManagement\Application\Commands\DeleteClinicCommand;
use App\Modules\TenantManagement\Application\Commands\UpdateClinicCommand;
use App\Modules\TenantManagement\Application\Handlers\CreateClinicCommandHandler;
use App\Modules\TenantManagement\Application\Handlers\DeleteClinicCommandHandler;
use App\Modules\TenantManagement\Application\Handlers\GetClinicQueryHandler;
use App\Modules\TenantManagement\Application\Handlers\ListClinicsQueryHandler;
use App\Modules\TenantManagement\Application\Handlers\UpdateClinicCommandHandler;
use App\Modules\TenantManagement\Application\Queries\GetClinicQuery;
use App\Modules\TenantManagement\Application\Queries\ListClinicsQuery;
use App\Modules\TenantManagement\Domain\Clinics\ClinicStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ClinicController
{
    public function create(Request $request, CreateClinicCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:160'],
            'contact_email' => ['nullable', 'email:rfc,dns', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'city_code' => ['nullable', 'string', 'max:64'],
            'district_code' => ['nullable', 'string', 'max:64'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        /** @var array<string, mixed> $validated */
        $clinic = $handler->handle(new CreateClinicCommand($validated));

        return response()->json([
            'status' => 'clinic_created',
            'data' => $clinic->toArray(),
        ], 201);
    }

    public function delete(string $clinicId, DeleteClinicCommandHandler $handler): JsonResponse
    {
        $clinic = $handler->handle(new DeleteClinicCommand($clinicId));

        return response()->json([
            'status' => 'clinic_deleted',
            'data' => $clinic->toArray(),
        ]);
    }

    public function list(Request $request, ListClinicsQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:'.implode(',', ClinicStatus::all())],
        ]);
        /** @var array<string, mixed> $validated */
        $items = [];

        foreach ($handler->handle(new ListClinicsQuery(
            search: $this->nullableString($validated, 'q'),
            status: $this->nullableString($validated, 'status'),
        )) as $clinic) {
            $items[] = $clinic->toArray();
        }

        return response()->json([
            'data' => $items,
        ]);
    }

    public function show(string $clinicId, GetClinicQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetClinicQuery($clinicId))->toArray(),
        ]);
    }

    public function update(string $clinicId, Request $request, UpdateClinicCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'filled', 'string', 'max:32'],
            'name' => ['sometimes', 'filled', 'string', 'max:160'],
            'contact_email' => ['sometimes', 'nullable', 'email:rfc,dns', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'city_code' => ['sometimes', 'nullable', 'string', 'max:64'],
            'district_code' => ['sometimes', 'nullable', 'string', 'max:64'],
            'address_line_1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);

        $clinic = $handler->handle(new UpdateClinicCommand($clinicId, $validated));

        return response()->json([
            'status' => 'clinic_updated',
            'data' => $clinic->toArray(),
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
}
