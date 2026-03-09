<?php

namespace App\Modules\Patient\Presentation\Http\Controllers;

use App\Modules\Patient\Application\Commands\CreatePatientContactCommand;
use App\Modules\Patient\Application\Commands\DeletePatientContactCommand;
use App\Modules\Patient\Application\Commands\UpdatePatientContactCommand;
use App\Modules\Patient\Application\Handlers\CreatePatientContactCommandHandler;
use App\Modules\Patient\Application\Handlers\DeletePatientContactCommandHandler;
use App\Modules\Patient\Application\Handlers\ListPatientContactsQueryHandler;
use App\Modules\Patient\Application\Handlers\UpdatePatientContactCommandHandler;
use App\Modules\Patient\Application\Queries\ListPatientContactsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PatientContactController
{
    public function create(string $patientId, Request $request, CreatePatientContactCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $contact = $handler->handle(new CreatePatientContactCommand($patientId, $validated));

        return response()->json([
            'status' => 'patient_contact_created',
            'data' => $contact->toArray(),
        ], 201);
    }

    public function delete(
        string $patientId,
        string $contactId,
        DeletePatientContactCommandHandler $handler,
    ): JsonResponse {
        $contact = $handler->handle(new DeletePatientContactCommand($patientId, $contactId));

        return response()->json([
            'status' => 'patient_contact_deleted',
            'data' => $contact->toArray(),
        ]);
    }

    public function list(string $patientId, ListPatientContactsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($contact): array => $contact->toArray(),
                $handler->handle(new ListPatientContactsQuery($patientId)),
            ),
        ]);
    }

    public function update(
        string $patientId,
        string $contactId,
        Request $request,
        UpdatePatientContactCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $contact = $handler->handle(new UpdatePatientContactCommand($patientId, $contactId, $validated));

        return response()->json([
            'status' => 'patient_contact_updated',
            'data' => $contact->toArray(),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function createRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'relationship' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32', 'required_without:email'],
            'email' => ['nullable', 'email:rfc,dns', 'max:255', 'required_without:phone'],
            'is_primary' => ['sometimes', 'boolean'],
            'is_emergency' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
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
     * @return array<string, array<int, string>>
     */
    private function updateRules(): array
    {
        return [
            'name' => ['sometimes', 'filled', 'string', 'max:160'],
            'relationship' => ['sometimes', 'nullable', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'email' => ['sometimes', 'nullable', 'email:rfc,dns', 'max:255'],
            'is_primary' => ['sometimes', 'boolean'],
            'is_emergency' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
