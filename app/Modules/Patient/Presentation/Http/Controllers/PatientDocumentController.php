<?php

namespace App\Modules\Patient\Presentation\Http\Controllers;

use App\Modules\Patient\Application\Commands\DeletePatientDocumentCommand;
use App\Modules\Patient\Application\Commands\UploadPatientDocumentCommand;
use App\Modules\Patient\Application\Handlers\DeletePatientDocumentCommandHandler;
use App\Modules\Patient\Application\Handlers\GetPatientDocumentQueryHandler;
use App\Modules\Patient\Application\Handlers\ListPatientDocumentsQueryHandler;
use App\Modules\Patient\Application\Handlers\UploadPatientDocumentCommandHandler;
use App\Modules\Patient\Application\Queries\GetPatientDocumentQuery;
use App\Modules\Patient\Application\Queries\ListPatientDocumentsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final class PatientDocumentController
{
    public function delete(
        string $patientId,
        string $docId,
        DeletePatientDocumentCommandHandler $handler,
    ): JsonResponse {
        $document = $handler->handle(new DeletePatientDocumentCommand($patientId, $docId));

        return response()->json([
            'status' => 'patient_document_deleted',
            'data' => $document->toArray(),
        ]);
    }

    public function list(string $patientId, ListPatientDocumentsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($document): array => $document->toArray(),
                $handler->handle(new ListPatientDocumentsQuery($patientId)),
            ),
        ]);
    }

    public function show(string $patientId, string $docId, GetPatientDocumentQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetPatientDocumentQuery($patientId, $docId))->toArray(),
        ]);
    }

    public function upload(string $patientId, Request $request, UploadPatientDocumentCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'title' => ['nullable', 'string', 'max:255'],
            'document_type' => ['nullable', 'string', 'max:64'],
        ]);
        /** @var UploadedFile $document */
        $document = $request->file('document');

        $uploadedDocument = $handler->handle(new UploadPatientDocumentCommand(
            patientId: $patientId,
            document: $document,
            title: $this->nullableString($validated, 'title'),
            documentType: $this->nullableString($validated, 'document_type'),
        ));

        return response()->json([
            'status' => 'patient_document_uploaded',
            'data' => $uploadedDocument->toArray(),
        ], 201);
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function nullableString(array $validated, string $key): ?string
    {
        /** @var mixed $value */
        $value = $validated[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
