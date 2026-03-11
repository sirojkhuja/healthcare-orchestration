<?php

namespace App\Modules\Insurance\Infrastructure\Persistence;

use App\Modules\Insurance\Application\Contracts\ClaimRepository;
use App\Modules\Insurance\Application\Data\ClaimAttachmentData;
use App\Modules\Insurance\Application\Data\ClaimData;
use App\Modules\Insurance\Application\Data\ClaimSearchCriteria;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseClaimRepository implements ClaimRepository
{
    public function __construct(
        private readonly ClaimRecordMapper $claimRecordMapper,
    ) {}

    #[\Override]
    public function allocateClaimNumber(string $tenantId): string
    {
        /** @var string $claimNumber */
        $claimNumber = DB::transaction(function () use ($tenantId): string {
            $now = CarbonImmutable::now();

            DB::table('claim_number_sequences')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'current_value' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $record = DB::table('claim_number_sequences')
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            $currentValue = $record instanceof stdClass && is_numeric($record->current_value ?? null)
                ? (int) $record->current_value
                : 0;
            $nextValue = $currentValue + 1;

            DB::table('claim_number_sequences')
                ->where('tenant_id', $tenantId)
                ->update([
                    'current_value' => $nextValue,
                    'updated_at' => $now,
                ]);

            return sprintf('CLM-%06d', $nextValue);
        });

        return $claimNumber;
    }

    #[\Override]
    public function create(string $tenantId, array $attributes): ClaimData
    {
        $claimId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('claims')->insert([
            'id' => $claimId,
            'tenant_id' => $tenantId,
            'payer_id' => $attributes['payer_id'],
            'patient_id' => $attributes['patient_id'],
            'invoice_id' => $attributes['invoice_id'],
            'patient_policy_id' => $attributes['patient_policy_id'],
            'claim_number' => $attributes['claim_number'],
            'payer_code' => $attributes['payer_code'],
            'payer_name' => $attributes['payer_name'],
            'payer_insurance_code' => $attributes['payer_insurance_code'],
            'patient_display_name' => $attributes['patient_display_name'],
            'invoice_number' => $attributes['invoice_number'],
            'patient_policy_number' => $attributes['patient_policy_number'],
            'patient_member_number' => $attributes['patient_member_number'],
            'patient_group_number' => $attributes['patient_group_number'],
            'patient_plan_name' => $attributes['patient_plan_name'],
            'currency' => $attributes['currency'],
            'service_date' => $attributes['service_date'],
            'billed_amount' => $attributes['billed_amount'],
            'approved_amount' => $attributes['approved_amount'],
            'paid_amount' => $attributes['paid_amount'],
            'notes' => $attributes['notes'],
            'status' => $attributes['status'],
            'attachment_count' => $attributes['attachment_count'],
            'service_categories' => $this->jsonValue($attributes['service_categories']),
            'submitted_at' => $attributes['submitted_at'],
            'review_started_at' => $attributes['review_started_at'],
            'approved_at' => $attributes['approved_at'],
            'denied_at' => $attributes['denied_at'],
            'paid_at' => $attributes['paid_at'],
            'denial_reason' => $attributes['denial_reason'],
            'last_transition' => $this->jsonValue($attributes['last_transition']),
            'adjudication_history' => $this->jsonValue($attributes['adjudication_history']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $claimId)
            ?? throw new \LogicException('Created claim could not be reloaded.');
    }

    #[\Override]
    public function createAttachment(string $tenantId, string $claimId, array $attributes): ClaimAttachmentData
    {
        $attachmentId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('claim_attachments')->insert([
            'id' => $attachmentId,
            'tenant_id' => $tenantId,
            'claim_id' => $claimId,
            'attachment_type' => $attributes['attachment_type'],
            'notes' => $attributes['notes'],
            'file_name' => $attributes['file_name'],
            'mime_type' => $attributes['mime_type'],
            'size_bytes' => $attributes['size_bytes'],
            'disk' => $attributes['disk'],
            'path' => $attributes['path'],
            'uploaded_at' => $attributes['uploaded_at'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->refreshAttachmentCount($tenantId, $claimId);

        return $this->findAttachment($tenantId, $claimId, $attachmentId)
            ?? throw new \LogicException('Created claim attachment could not be reloaded.');
    }

    #[\Override]
    public function deleteAttachment(string $tenantId, string $claimId, string $attachmentId): bool
    {
        $deleted = DB::table('claim_attachments')
            ->where('tenant_id', $tenantId)
            ->where('claim_id', $claimId)
            ->where('id', $attachmentId)
            ->delete() > 0;

        if ($deleted) {
            $this->refreshAttachmentCount($tenantId, $claimId);
        }

        return $deleted;
    }

    #[\Override]
    public function findAttachment(string $tenantId, string $claimId, string $attachmentId): ?ClaimAttachmentData
    {
        $row = DB::table('claim_attachments')
            ->where('tenant_id', $tenantId)
            ->where('claim_id', $claimId)
            ->where('id', $attachmentId)
            ->first();

        return $row instanceof stdClass ? $this->claimRecordMapper->toAttachmentData($row) : null;
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $claimId, bool $withDeleted = false): ?ClaimData
    {
        $query = $this->baseQuery($tenantId)->where('claims.id', $claimId);

        if (! $withDeleted) {
            $query->whereNull('claims.deleted_at');
        }

        $row = $query->first();

        return $row instanceof stdClass ? $this->claimRecordMapper->toClaimData($row) : null;
    }

    #[\Override]
    public function listAttachments(string $tenantId, string $claimId): array
    {
        /** @var list<stdClass> $rows */
        $rows = DB::table('claim_attachments')
            ->where('tenant_id', $tenantId)
            ->where('claim_id', $claimId)
            ->orderByDesc('uploaded_at')
            ->orderByDesc('id')
            ->get()
            ->all();

        return array_map($this->claimRecordMapper->toAttachmentData(...), $rows);
    }

    #[\Override]
    public function search(string $tenantId, ClaimSearchCriteria $criteria): array
    {
        $query = $this->baseQuery($tenantId)->whereNull('claims.deleted_at');

        foreach ([
            'status' => $criteria->status,
            'payer_id' => $criteria->payerId,
            'patient_id' => $criteria->patientId,
            'invoice_id' => $criteria->invoiceId,
        ] as $column => $value) {
            if ($value !== null) {
                $query->where('claims.'.$column, $value);
            }
        }

        if ($criteria->serviceDateFrom !== null) {
            $query->whereDate('claims.service_date', '>=', $criteria->serviceDateFrom);
        }

        if ($criteria->serviceDateTo !== null) {
            $query->whereDate('claims.service_date', '<=', $criteria->serviceDateTo);
        }

        if ($criteria->createdFrom !== null) {
            $query->where('claims.created_at', '>=', CarbonImmutable::parse($criteria->createdFrom)->startOfDay());
        }

        if ($criteria->createdTo !== null) {
            $query->where('claims.created_at', '<=', CarbonImmutable::parse($criteria->createdTo)->endOfDay());
        }

        if ($criteria->query !== null && trim($criteria->query) !== '') {
            $pattern = '%'.mb_strtolower(trim($criteria->query)).'%';
            $query->where(function (Builder $builder) use ($pattern): void {
                $builder
                    ->whereRaw('LOWER(claims.claim_number) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(claims.invoice_number) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(claims.patient_display_name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(claims.payer_code) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(claims.payer_name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(claims.patient_policy_number, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(claims.notes, \'\')) LIKE ?', [$pattern]);
            });
        }

        /** @var list<stdClass> $rows */
        $rows = $query
            ->orderByRaw('COALESCE(claims.paid_at, claims.approved_at, claims.denied_at, claims.review_started_at, claims.submitted_at, claims.created_at) DESC')
            ->orderByDesc('claims.claim_number')
            ->limit($criteria->limit)
            ->get()
            ->all();

        return array_map(fn (stdClass $row): ClaimData => $this->claimRecordMapper->toClaimData($row), $rows);
    }

    #[\Override]
    public function softDelete(string $tenantId, string $claimId, CarbonImmutable $deletedAt): bool
    {
        return DB::table('claims')
            ->where('tenant_id', $tenantId)
            ->where('id', $claimId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
            ]) > 0;
    }

    #[\Override]
    public function update(string $tenantId, string $claimId, array $updates): ?ClaimData
    {
        if ($updates === []) {
            return $this->findInTenant($tenantId, $claimId);
        }

        unset($updates['claim_id'], $updates['tenant_id']);

        foreach (['service_categories', 'last_transition', 'adjudication_history'] as $jsonColumn) {
            if (array_key_exists($jsonColumn, $updates)) {
                $updates[$jsonColumn] = $this->jsonValue($updates[$jsonColumn]);
            }
        }

        $updates['updated_at'] = CarbonImmutable::now();

        DB::table('claims')
            ->where('tenant_id', $tenantId)
            ->where('id', $claimId)
            ->whereNull('deleted_at')
            ->update($updates);

        return $this->findInTenant($tenantId, $claimId, true);
    }

    private function baseQuery(string $tenantId): Builder
    {
        return DB::table('claims')
            ->where('claims.tenant_id', $tenantId)
            ->select([
                'claims.id as claim_id',
                'claims.tenant_id',
                'claims.claim_number',
                'claims.payer_id',
                'claims.payer_code',
                'claims.payer_name',
                'claims.payer_insurance_code',
                'claims.patient_id',
                'claims.patient_display_name',
                'claims.invoice_id',
                'claims.invoice_number',
                'claims.patient_policy_id',
                'claims.patient_policy_number',
                'claims.patient_member_number',
                'claims.patient_group_number',
                'claims.patient_plan_name',
                'claims.currency',
                'claims.service_date',
                'claims.billed_amount',
                'claims.approved_amount',
                'claims.paid_amount',
                'claims.notes',
                'claims.status',
                'claims.attachment_count',
                'claims.service_categories',
                'claims.submitted_at',
                'claims.review_started_at',
                'claims.approved_at',
                'claims.denied_at',
                'claims.paid_at',
                'claims.denial_reason',
                'claims.last_transition',
                'claims.adjudication_history',
                'claims.deleted_at',
                'claims.created_at',
                'claims.updated_at',
            ]);
    }

    private function jsonValue(mixed $value): ?string
    {
        if (! is_array($value)) {
            return null;
        }

        /** @var array<array-key, mixed> $value */
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function refreshAttachmentCount(string $tenantId, string $claimId): void
    {
        /** @var mixed $count */
        $count = DB::table('claim_attachments')
            ->where('tenant_id', $tenantId)
            ->where('claim_id', $claimId)
            ->count();

        DB::table('claims')
            ->where('tenant_id', $tenantId)
            ->where('id', $claimId)
            ->update([
                'attachment_count' => is_numeric($count) ? (int) $count : 0,
                'updated_at' => CarbonImmutable::now(),
            ]);
    }
}
