<?php

namespace App\Modules\Insurance\Application\Contracts;

use App\Modules\Insurance\Application\Data\ClaimAttachmentData;
use App\Modules\Insurance\Application\Data\ClaimData;
use App\Modules\Insurance\Application\Data\ClaimSearchCriteria;
use Carbon\CarbonImmutable;

interface ClaimRepository
{
    public function allocateClaimNumber(string $tenantId): string;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $tenantId, array $attributes): ClaimData;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createAttachment(string $tenantId, string $claimId, array $attributes): ClaimAttachmentData;

    public function deleteAttachment(string $tenantId, string $claimId, string $attachmentId): bool;

    public function findAttachment(string $tenantId, string $claimId, string $attachmentId): ?ClaimAttachmentData;

    public function findInTenant(string $tenantId, string $claimId, bool $withDeleted = false): ?ClaimData;

    /**
     * @return list<ClaimAttachmentData>
     */
    public function listAttachments(string $tenantId, string $claimId): array;

    /**
     * @return list<ClaimData>
     */
    public function search(string $tenantId, ClaimSearchCriteria $criteria): array;

    public function softDelete(string $tenantId, string $claimId, CarbonImmutable $deletedAt): bool;

    /**
     * @param  array<string, mixed>  $updates
     */
    public function update(string $tenantId, string $claimId, array $updates): ?ClaimData;
}
