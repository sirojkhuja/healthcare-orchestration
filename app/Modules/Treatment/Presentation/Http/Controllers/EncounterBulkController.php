<?php

namespace App\Modules\Treatment\Presentation\Http\Controllers;

use App\Modules\Treatment\Application\Commands\BulkUpdateEncountersCommand;
use App\Modules\Treatment\Application\Handlers\BulkUpdateEncountersCommandHandler;
use App\Modules\Treatment\Domain\Encounters\EncounterStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class EncounterBulkController
{
    public function update(Request $request, BulkUpdateEncountersCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $changes = $this->filterAllowedChanges($validated['changes'] ?? []);

        if ($changes === []) {
            throw ValidationException::withMessages([
                'changes' => ['At least one updatable field is required.'],
            ]);
        }

        $encounterIdsInput = [];

        if (array_key_exists('encounter_ids', $validated) && is_array($validated['encounter_ids'])) {
            $encounterIdsInput = $validated['encounter_ids'];
        }

        /** @var array<array-key, mixed> $encounterIdsInput */
        /** @var list<string> $encounterIds */
        $encounterIds = array_values(array_filter(
            $encounterIdsInput,
            static fn (mixed $encounterId): bool => is_string($encounterId),
        ));

        $result = $handler->handle(new BulkUpdateEncountersCommand($encounterIds, $changes));

        return response()->json([
            'status' => 'encounters_bulk_updated',
            'data' => $result->toArray(),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function updateRules(): array
    {
        return [
            'encounter_ids' => ['required', 'array', 'min:1', 'max:100'],
            'encounter_ids.*' => ['required', 'uuid', 'distinct'],
            'changes' => ['required', 'array'],
            'changes.status' => ['sometimes', 'filled', 'string', 'in:'.implode(',', EncounterStatus::all())],
            'changes.provider_id' => ['sometimes', 'filled', 'uuid'],
            'changes.clinic_id' => ['sometimes', 'nullable', 'uuid'],
            'changes.room_id' => ['sometimes', 'nullable', 'uuid'],
            'changes.encountered_at' => ['sometimes', 'filled', 'date'],
            'changes.timezone' => ['sometimes', 'filled', 'timezone:all'],
        ];
    }

    /**
     * @return array{
     *     status?: string,
     *     provider_id?: string,
     *     clinic_id?: ?string,
     *     room_id?: ?string,
     *     encountered_at?: string,
     *     timezone?: string
     * }
     */
    private function filterAllowedChanges(mixed $changes): array
    {
        if (! is_array($changes)) {
            return [];
        }

        $normalized = [];

        if (array_key_exists('status', $changes) && is_string($changes['status'])) {
            $normalized['status'] = $changes['status'];
        }

        if (array_key_exists('provider_id', $changes) && is_string($changes['provider_id'])) {
            $normalized['provider_id'] = $changes['provider_id'];
        }

        if (array_key_exists('clinic_id', $changes) && (is_string($changes['clinic_id']) || $changes['clinic_id'] === null)) {
            $normalized['clinic_id'] = $changes['clinic_id'];
        }

        if (array_key_exists('room_id', $changes) && (is_string($changes['room_id']) || $changes['room_id'] === null)) {
            $normalized['room_id'] = $changes['room_id'];
        }

        if (array_key_exists('encountered_at', $changes) && is_string($changes['encountered_at'])) {
            $normalized['encountered_at'] = $changes['encountered_at'];
        }

        if (array_key_exists('timezone', $changes) && is_string($changes['timezone'])) {
            $normalized['timezone'] = $changes['timezone'];
        }

        return $normalized;
    }
}
