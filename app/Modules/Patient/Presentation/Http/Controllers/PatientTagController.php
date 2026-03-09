<?php

namespace App\Modules\Patient\Presentation\Http\Controllers;

use App\Modules\Patient\Application\Commands\SetPatientTagsCommand;
use App\Modules\Patient\Application\Handlers\ListPatientTagsQueryHandler;
use App\Modules\Patient\Application\Handlers\SetPatientTagsCommandHandler;
use App\Modules\Patient\Application\Queries\ListPatientTagsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PatientTagController
{
    public function list(string $patientId, ListPatientTagsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new ListPatientTagsQuery($patientId))->toArray(),
        ]);
    }

    public function update(string $patientId, Request $request, SetPatientTagsCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'tags' => ['required', 'array'],
            'tags.*' => ['nullable', 'string', 'max:120'],
        ]);
        /** @var array<string, mixed> $validated */
        /** @var mixed $rawTags */
        $rawTags = $validated['tags'] ?? [];
        /** @var list<string> $tags */
        $tags = is_array($rawTags) ? array_values(array_filter($rawTags, 'is_string')) : [];
        $tagList = $handler->handle(new SetPatientTagsCommand($patientId, $tags));

        return response()->json([
            'status' => 'patient_tags_updated',
            'data' => $tagList->toArray(),
        ]);
    }
}
