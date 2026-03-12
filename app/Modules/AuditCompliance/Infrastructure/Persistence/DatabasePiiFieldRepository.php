<?php

namespace App\Modules\AuditCompliance\Infrastructure\Persistence;

use App\Modules\AuditCompliance\Application\Contracts\PiiFieldRepository;
use App\Modules\AuditCompliance\Application\Data\PiiFieldData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DatabasePiiFieldRepository implements PiiFieldRepository
{
    #[\Override]
    public function findActiveByIds(string $tenantId, array $fieldIds): array
    {
        if ($fieldIds === []) {
            return $this->activeForTenant();
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, PiiFieldRecord> $records */
        $records = PiiFieldRecord::query()
            ->whereIn('id', array_values(array_unique($fieldIds)))
            ->where('status', 'active')
            ->orderBy('object_type')
            ->orderBy('field_path')
            ->orderBy('created_at')
            ->get();

        return $this->mapRecords($records->all());
    }

    #[\Override]
    public function listForTenant(string $tenantId): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, PiiFieldRecord> $records */
        $records = PiiFieldRecord::query()
            ->orderBy('object_type')
            ->orderBy('field_path')
            ->orderBy('created_at')
            ->get();

        return $this->mapRecords($records->all());
    }

    #[\Override]
    public function markReencrypted(string $tenantId, array $fieldIds, CarbonImmutable $now): array
    {
        $query = PiiFieldRecord::query()->where('status', 'active');

        if ($fieldIds !== []) {
            $query->whereIn('id', array_values(array_unique($fieldIds)));
        }

        DB::transaction(function () use ($query, $now): void {
            $query->update([
                'last_reencrypted_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return $fieldIds === []
            ? $this->activeForTenant()
            : $this->findActiveByIds($tenantId, $fieldIds);
    }

    #[\Override]
    public function replace(string $tenantId, array $fields, CarbonImmutable $now): array
    {
        /** @psalm-suppress MixedAssignment */
        $result = DB::transaction(function () use ($tenantId, $fields, $now): array {
            $keepFingerprints = [];

            foreach ($fields as $field) {
                $objectType = $this->normalize($field->objectType);
                $fieldPath = $this->normalize($field->fieldPath);
                $keepFingerprints[] = $this->fingerprint($objectType, $fieldPath);

                /** @var PiiFieldRecord $record */
                $record = PiiFieldRecord::query()->updateOrCreate(
                    [
                        'tenant_id' => strtolower($tenantId),
                        'object_type' => $objectType,
                        'field_path' => $fieldPath,
                    ],
                    [
                        'classification' => $this->normalize($field->classification),
                        'encryption_profile' => $this->normalize($field->encryptionProfile),
                        'status' => 'active',
                        'notes' => $this->nullableTrimmed($field->notes),
                        'updated_at' => $now,
                    ],
                );

                if ($record->getAttribute('created_at') === null) {
                    $record->setAttribute('created_at', $now);
                }

                $record->save();
            }

            /** @var \Illuminate\Database\Eloquent\Collection<int, PiiFieldRecord> $records */
            $records = PiiFieldRecord::query()->get();

            foreach ($records as $record) {
                $fingerprint = $this->fingerprint(
                    $this->normalize($this->stringValue($record->getAttribute('object_type'))),
                    $this->normalize($this->stringValue($record->getAttribute('field_path'))),
                );

                $status = in_array($fingerprint, $keepFingerprints, true) ? 'active' : 'retired';

                if ($this->stringValue($record->getAttribute('status')) !== $status) {
                    $record->forceFill([
                        'status' => $status,
                        'updated_at' => $now,
                    ])->save();
                }
            }

            return $this->listForTenant($tenantId);
        });

        /** @var list<PiiFieldData> $result */
        return $result;
    }

    #[\Override]
    public function rotateKeys(string $tenantId, array $fieldIds, CarbonImmutable $now): array
    {
        $query = PiiFieldRecord::query()->where('status', 'active');

        if ($fieldIds !== []) {
            $query->whereIn('id', array_values(array_unique($fieldIds)));
        }

        DB::transaction(function () use ($query, $now): void {
            /** @var \Illuminate\Database\Eloquent\Collection<int, PiiFieldRecord> $records */
            $records = $query->lockForUpdate()->get();

            foreach ($records as $record) {
                $record->forceFill([
                    'key_version' => $this->intValue($record->getAttribute('key_version')) + 1,
                    'last_rotated_at' => $now,
                    'updated_at' => $now,
                ])->save();
            }
        });

        return $fieldIds === []
            ? $this->activeForTenant()
            : $this->findActiveByIds($tenantId, $fieldIds);
    }

    /**
     * @param  array<int, PiiFieldRecord>  $records
     * @return list<PiiFieldData>
     */
    private function mapRecords(array $records): array
    {
        /** @var list<PiiFieldData> $fields */
        $fields = array_map(
            fn (PiiFieldRecord $record) => $this->toData($record),
            $records,
        );

        return $fields;
    }

    /**
     * @return list<PiiFieldData>
     */
    private function activeForTenant(): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, PiiFieldRecord> $records */
        $records = PiiFieldRecord::query()
            ->where('status', 'active')
            ->orderBy('object_type')
            ->orderBy('field_path')
            ->orderBy('created_at')
            ->get();

        return $this->mapRecords($records->all());
    }

    private function fingerprint(string $objectType, string $fieldPath): string
    {
        return $objectType.'::'.$fieldPath;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->trim()->lower()->value();
    }

    private function nullableTrimmed(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function toData(PiiFieldRecord $record): PiiFieldData
    {
        return new PiiFieldData(
            fieldId: $this->stringValue($record->getAttribute('id')),
            tenantId: $this->stringValue($record->getAttribute('tenant_id')),
            objectType: $this->stringValue($record->getAttribute('object_type')),
            fieldPath: $this->stringValue($record->getAttribute('field_path')),
            classification: $this->stringValue($record->getAttribute('classification')),
            encryptionProfile: $this->stringValue($record->getAttribute('encryption_profile')),
            keyVersion: $this->intValue($record->getAttribute('key_version')),
            status: $this->stringValue($record->getAttribute('status')),
            notes: $this->nullableString($record->getAttribute('notes')),
            lastRotatedAt: $record->getAttribute('last_rotated_at') !== null
                ? CarbonImmutable::parse($this->stringValue($record->getAttribute('last_rotated_at')))
                : null,
            lastReencryptedAt: $record->getAttribute('last_reencrypted_at') !== null
                ? CarbonImmutable::parse($this->stringValue($record->getAttribute('last_reencrypted_at')))
                : null,
            createdAt: CarbonImmutable::parse($this->stringValue($record->getAttribute('created_at'))),
            updatedAt: CarbonImmutable::parse($this->stringValue($record->getAttribute('updated_at'))),
        );
    }
}
