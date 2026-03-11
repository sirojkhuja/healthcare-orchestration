<?php

namespace App\Modules\Insurance\Presentation\Http\Controllers;

use App\Modules\Insurance\Application\Commands\DeleteClaimAttachmentCommand;
use App\Modules\Insurance\Application\Commands\UploadClaimAttachmentCommand;
use App\Modules\Insurance\Application\Handlers\DeleteClaimAttachmentCommandHandler;
use App\Modules\Insurance\Application\Handlers\ListClaimAttachmentsQueryHandler;
use App\Modules\Insurance\Application\Handlers\UploadClaimAttachmentCommandHandler;
use App\Modules\Insurance\Application\Queries\ListClaimAttachmentsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final class ClaimAttachmentController
{
    public function delete(string $claimId, string $attachmentId, DeleteClaimAttachmentCommandHandler $handler): JsonResponse
    {
        $attachment = $handler->handle(new DeleteClaimAttachmentCommand($claimId, $attachmentId));

        return response()->json([
            'status' => 'claim_attachment_deleted',
            'data' => $attachment->toArray(),
        ]);
    }

    public function list(string $claimId, ListClaimAttachmentsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($attachment): array => $attachment->toArray(),
                $handler->handle(new ListClaimAttachmentsQuery($claimId)),
            ),
        ]);
    }

    public function upload(string $claimId, Request $request, UploadClaimAttachmentCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file'],
            'attachment_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
        /** @var array<string, mixed> $validated */
        $file = $validated['file'];
        \assert($file instanceof UploadedFile);
        $attachment = $handler->handle(new UploadClaimAttachmentCommand(
            claimId: $claimId,
            file: $file,
            attachmentType: array_key_exists('attachment_type', $validated) && is_string($validated['attachment_type'])
                ? $validated['attachment_type']
                : null,
            notes: array_key_exists('notes', $validated) && is_string($validated['notes'])
                ? $validated['notes']
                : null,
        ));

        return response()->json([
            'status' => 'claim_attachment_uploaded',
            'data' => $attachment->toArray(),
        ], 201);
    }
}
