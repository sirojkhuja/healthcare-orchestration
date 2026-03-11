<?php

namespace App\Modules\Insurance\Infrastructure\Persistence;

use App\Modules\Insurance\Application\Data\ClaimAttachmentData;
use App\Modules\Insurance\Application\Data\ClaimData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use stdClass;

final class ClaimRecordMapper
{
    public function decimalString(mixed $value): string
    {
        if (is_int($value)) {
            return sprintf('%d.00', $value);
        }

        if (is_float($value)) {
            return number_format($value, 2, '.', '');
        }

        if (! is_string($value)) {
            return '0.00';
        }

        $parts = explode('.', $value, 2);

        return $parts[0].'.'.str_pad($parts[1] ?? '', 2, '0');
    }

    /**
     * @return list<string>
     */
    public function stringList(mixed $value): array
    {
        $array = $this->jsonArray($value);

        if ($array === null) {
            return [];
        }

        $result = [];

        array_walk($array, static function (mixed $item) use (&$result): void {
            if (is_string($item) && $item !== '') {
                $result[] = $item;
            }
        });

        return $result;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function jsonArray(mixed $value): ?array
    {
        if (is_array($value)) {
            /** @var array<array-key, mixed> $value */
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return null;
        }

        /** @var array<array-key, mixed> $decoded */
        return $decoded;
    }

    public function toAttachmentData(stdClass $row): ClaimAttachmentData
    {
        return new ClaimAttachmentData(
            attachmentId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            claimId: $this->stringValue($row->claim_id ?? null),
            attachmentType: $this->nullableString($row->attachment_type ?? null),
            notes: $this->nullableString($row->notes ?? null),
            fileName: $this->stringValue($row->file_name ?? null),
            mimeType: $this->stringValue($row->mime_type ?? null),
            sizeBytes: $this->intValue($row->size_bytes ?? null),
            disk: $this->stringValue($row->disk ?? null),
            path: $this->stringValue($row->path ?? null),
            uploadedAt: $this->dateTime($row->uploaded_at ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }

    public function toClaimData(stdClass $row): ClaimData
    {
        return new ClaimData(
            claimId: $this->stringValue($row->claim_id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            claimNumber: $this->stringValue($row->claim_number ?? null),
            payerId: $this->stringValue($row->payer_id ?? null),
            payerCode: $this->stringValue($row->payer_code ?? null),
            payerName: $this->stringValue($row->payer_name ?? null),
            payerInsuranceCode: $this->stringValue($row->payer_insurance_code ?? null),
            patientId: $this->stringValue($row->patient_id ?? null),
            patientDisplayName: $this->stringValue($row->patient_display_name ?? null),
            invoiceId: $this->stringValue($row->invoice_id ?? null),
            invoiceNumber: $this->stringValue($row->invoice_number ?? null),
            patientPolicyId: $this->nullableString($row->patient_policy_id ?? null),
            patientPolicyNumber: $this->nullableString($row->patient_policy_number ?? null),
            patientMemberNumber: $this->nullableString($row->patient_member_number ?? null),
            patientGroupNumber: $this->nullableString($row->patient_group_number ?? null),
            patientPlanName: $this->nullableString($row->patient_plan_name ?? null),
            currency: $this->stringValue($row->currency ?? null),
            serviceDate: $this->dateTime($row->service_date ?? null),
            billedAmount: $this->decimalString($row->billed_amount ?? null),
            approvedAmount: $this->nullableDecimalString($row->approved_amount ?? null),
            paidAmount: $this->nullableDecimalString($row->paid_amount ?? null),
            notes: $this->nullableString($row->notes ?? null),
            status: $this->stringValue($row->status ?? null),
            attachmentCount: $this->intValue($row->attachment_count ?? null),
            serviceCategories: $this->stringList($row->service_categories ?? null),
            submittedAt: $this->nullableDateTime($row->submitted_at ?? null),
            reviewStartedAt: $this->nullableDateTime($row->review_started_at ?? null),
            approvedAt: $this->nullableDateTime($row->approved_at ?? null),
            deniedAt: $this->nullableDateTime($row->denied_at ?? null),
            paidAt: $this->nullableDateTime($row->paid_at ?? null),
            denialReason: $this->nullableString($row->denial_reason ?? null),
            lastTransition: $this->stringKeyedArray($this->jsonArray($row->last_transition ?? null)),
            adjudicationHistory: $this->listOfStringKeyedArrays($this->jsonArray($row->adjudication_history ?? null)),
            deletedAt: $this->nullableDateTime($row->deleted_at ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }

    private function dateTime(mixed $value): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse($this->stringValue($value));
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param  array<array-key, mixed>|null  $value
     * @return list<array<string, mixed>>
     */
    private function listOfStringKeyedArrays(?array $value): array
    {
        if ($value === null) {
            return [];
        }

        $result = [];

        array_walk($value, function (mixed $item) use (&$result): void {
            if (is_array($item)) {
                $mapped = $this->stringKeyedArray($item);

                if ($mapped !== null) {
                    $result[] = $mapped;
                }
            }
        });

        return $result;
    }

    private function nullableDateTime(mixed $value): ?CarbonImmutable
    {
        return $value === null ? null : $this->dateTime($value);
    }

    private function nullableDecimalString(mixed $value): ?string
    {
        return $value === null ? null : $this->decimalString($value);
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<array-key, mixed>|null  $value
     * @return array<string, mixed>|null
     */
    private function stringKeyedArray(?array $value): ?array
    {
        if ($value === null) {
            return null;
        }

        foreach (array_keys($value) as $key) {
            if (! is_string($key)) {
                return null;
            }
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
