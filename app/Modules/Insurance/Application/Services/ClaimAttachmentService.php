<?php

namespace App\Modules\Insurance\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Insurance\Application\Contracts\ClaimAttachmentStore;
use App\Modules\Insurance\Application\Contracts\ClaimRepository;
use App\Modules\Insurance\Application\Data\ClaimAttachmentData;
use App\Modules\Insurance\Application\Data\ClaimData;
use App\Shared\Application\Contracts\TenantContext;
use Illuminate\Http\UploadedFile;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ClaimAttachmentService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ClaimRepository $claimRepository,
        private readonly ClaimAttachmentStore $claimAttachmentStore,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function delete(string $claimId, string $attachmentId): ClaimAttachmentData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $this->claimOrFail($tenantId, $claimId);
        $attachment = $this->attachmentOrFail($tenantId, $claimId, $attachmentId);

        if (! $this->claimRepository->deleteAttachment($tenantId, $claimId, $attachmentId)) {
            throw new LogicException('Claim attachment deletion did not remove the stored record.');
        }

        $this->claimAttachmentStore->delete($attachment->disk, $attachment->path);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'claim_attachments.deleted',
            objectType: 'claim_attachment',
            objectId: $attachment->attachmentId,
            before: $attachment->toArray(),
            metadata: [
                'claim_id' => $claimId,
            ],
        ));

        return $attachment;
    }

    /**
     * @return list<ClaimAttachmentData>
     */
    public function list(string $claimId): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $this->claimOrFail($tenantId, $claimId);

        return $this->claimRepository->listAttachments($tenantId, $claimId);
    }

    public function upload(string $claimId, UploadedFile $file, ?string $attachmentType, ?string $notes): ClaimAttachmentData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $this->claimOrFail($tenantId, $claimId);
        $stored = $this->claimAttachmentStore->storeForClaim($tenantId, $claimId, $file);
        $attachment = $this->claimRepository->createAttachment($tenantId, $claimId, [
            'attachment_type' => $attachmentType !== null && trim($attachmentType) !== '' ? mb_strtolower(trim($attachmentType)) : null,
            'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            'file_name' => $stored->fileName,
            'mime_type' => $stored->mimeType,
            'size_bytes' => $stored->sizeBytes,
            'disk' => $stored->disk,
            'path' => $stored->path,
            'uploaded_at' => $stored->uploadedAt,
        ]);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'claim_attachments.uploaded',
            objectType: 'claim_attachment',
            objectId: $attachment->attachmentId,
            after: $attachment->toArray(),
            metadata: [
                'claim_id' => $claimId,
            ],
        ));

        return $attachment;
    }

    private function attachmentOrFail(string $tenantId, string $claimId, string $attachmentId): ClaimAttachmentData
    {
        $attachment = $this->claimRepository->findAttachment($tenantId, $claimId, $attachmentId);

        if (! $attachment instanceof ClaimAttachmentData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $attachment;
    }

    private function claimOrFail(string $tenantId, string $claimId): ClaimData
    {
        $claim = $this->claimRepository->findInTenant($tenantId, $claimId);

        if (! $claim instanceof ClaimData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $claim;
    }
}
