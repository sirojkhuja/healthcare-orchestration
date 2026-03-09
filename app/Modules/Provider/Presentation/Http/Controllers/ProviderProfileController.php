<?php

namespace App\Modules\Provider\Presentation\Http\Controllers;

use App\Modules\Provider\Application\Commands\UpdateProviderProfileCommand;
use App\Modules\Provider\Application\Handlers\GetProviderProfileQueryHandler;
use App\Modules\Provider\Application\Handlers\UpdateProviderProfileCommandHandler;
use App\Modules\Provider\Application\Queries\GetProviderProfileQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ProviderProfileController
{
    public function show(string $providerId, GetProviderProfileQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetProviderProfileQuery($providerId))->toArray(),
        ]);
    }

    public function update(
        string $providerId,
        Request $request,
        UpdateProviderProfileCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'professional_title' => ['sometimes', 'nullable', 'string', 'max:160'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'years_of_experience' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:80'],
            'department_id' => ['sometimes', 'nullable', 'uuid'],
            'room_id' => ['sometimes', 'nullable', 'uuid'],
            'is_accepting_new_patients' => ['sometimes', 'boolean'],
            'languages' => ['sometimes', 'array', 'max:12'],
            'languages.*' => ['string', 'max:80'],
        ]);
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $profile = $handler->handle(new UpdateProviderProfileCommand($providerId, $validated));

        return response()->json([
            'status' => 'provider_profile_updated',
            'data' => $profile->toArray(),
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
