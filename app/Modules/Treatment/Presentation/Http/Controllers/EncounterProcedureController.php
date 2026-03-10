<?php

namespace App\Modules\Treatment\Presentation\Http\Controllers;

use App\Modules\Treatment\Application\Commands\AddProcedureCommand;
use App\Modules\Treatment\Application\Commands\RemoveProcedureCommand;
use App\Modules\Treatment\Application\Handlers\AddProcedureCommandHandler;
use App\Modules\Treatment\Application\Handlers\ListProceduresQueryHandler;
use App\Modules\Treatment\Application\Handlers\RemoveProcedureCommandHandler;
use App\Modules\Treatment\Application\Queries\ListProceduresQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EncounterProcedureController
{
    public function create(string $encounterId, Request $request, AddProcedureCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $procedure = $handler->handle(new AddProcedureCommand($encounterId, $validated));

        return response()->json([
            'status' => 'encounter_procedure_added',
            'data' => $procedure->toArray(),
        ], 201);
    }

    public function delete(string $encounterId, string $procId, RemoveProcedureCommandHandler $handler): JsonResponse
    {
        $procedure = $handler->handle(new RemoveProcedureCommand($encounterId, $procId));

        return response()->json([
            'status' => 'encounter_procedure_removed',
            'data' => $procedure->toArray(),
        ]);
    }

    public function list(string $encounterId, ListProceduresQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($procedure): array => $procedure->toArray(),
                $handler->handle(new ListProceduresQuery($encounterId)),
            ),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function createRules(): array
    {
        return [
            'treatment_item_id' => ['sometimes', 'nullable', 'uuid'],
            'code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'display_name' => ['required', 'string', 'max:255'],
            'performed_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
