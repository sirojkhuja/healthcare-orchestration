<?php

namespace App\Modules\Treatment\Presentation\Http\Controllers;

use App\Modules\Treatment\Application\Commands\AddDiagnosisCommand;
use App\Modules\Treatment\Application\Commands\RemoveDiagnosisCommand;
use App\Modules\Treatment\Application\Handlers\AddDiagnosisCommandHandler;
use App\Modules\Treatment\Application\Handlers\ListDiagnosesQueryHandler;
use App\Modules\Treatment\Application\Handlers\RemoveDiagnosisCommandHandler;
use App\Modules\Treatment\Application\Queries\ListDiagnosesQuery;
use App\Modules\Treatment\Domain\Encounters\DiagnosisType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EncounterDiagnosisController
{
    public function create(string $encounterId, Request $request, AddDiagnosisCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $diagnosis = $handler->handle(new AddDiagnosisCommand($encounterId, $validated));

        return response()->json([
            'status' => 'encounter_diagnosis_added',
            'data' => $diagnosis->toArray(),
        ], 201);
    }

    public function delete(string $encounterId, string $dxId, RemoveDiagnosisCommandHandler $handler): JsonResponse
    {
        $diagnosis = $handler->handle(new RemoveDiagnosisCommand($encounterId, $dxId));

        return response()->json([
            'status' => 'encounter_diagnosis_removed',
            'data' => $diagnosis->toArray(),
        ]);
    }

    public function list(string $encounterId, ListDiagnosesQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($diagnosis): array => $diagnosis->toArray(),
                $handler->handle(new ListDiagnosesQuery($encounterId)),
            ),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function createRules(): array
    {
        return [
            'code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'display_name' => ['required', 'string', 'max:255'],
            'diagnosis_type' => ['required', 'string', 'in:'.implode(',', DiagnosisType::all())],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
