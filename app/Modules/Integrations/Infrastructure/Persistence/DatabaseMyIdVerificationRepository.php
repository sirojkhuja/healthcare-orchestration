<?php

namespace App\Modules\Integrations\Infrastructure\Persistence;

use App\Modules\Integrations\Application\Contracts\MyIdVerificationRepository;
use App\Modules\Integrations\Application\Data\MyIdVerificationData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseMyIdVerificationRepository implements MyIdVerificationRepository
{
    #[\Override]
    public function create(
        string $tenantId,
        string $webhookId,
        string $externalReference,
        string $providerReference,
        array $subject,
        array $metadata,
        CarbonImmutable $now,
    ): MyIdVerificationData {
        $id = (string) Str::uuid();

        DB::table('myid_verifications')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'webhook_id' => $webhookId,
            'external_reference' => $externalReference,
            'provider_reference' => $providerReference,
            'status' => 'pending',
            'subject' => json_encode($subject, JSON_THROW_ON_ERROR),
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'result_payload' => null,
            'completed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findByProviderReference($tenantId, $providerReference)
            ?? throw new \LogicException('Created MyID verification could not be reloaded.');
    }

    #[\Override]
    public function findByProviderReference(string $tenantId, string $providerReference): ?MyIdVerificationData
    {
        $row = DB::table('myid_verifications')
            ->where('tenant_id', $tenantId)
            ->where('provider_reference', $providerReference)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function complete(
        string $tenantId,
        string $providerReference,
        string $status,
        array $resultPayload,
        CarbonImmutable $completedAt,
        CarbonImmutable $updatedAt,
    ): ?MyIdVerificationData {
        $updated = DB::table('myid_verifications')
            ->where('tenant_id', $tenantId)
            ->where('provider_reference', $providerReference)
            ->where('status', 'pending')
            ->update([
                'status' => $status,
                'result_payload' => json_encode($resultPayload, JSON_THROW_ON_ERROR),
                'completed_at' => $completedAt,
                'updated_at' => $updatedAt,
            ]);

        if ($updated > 0) {
            return $this->findByProviderReference($tenantId, $providerReference);
        }

        return $this->findByProviderReference($tenantId, $providerReference);
    }

    private function dateTime(mixed $value): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse(is_string($value) ? $value : '');
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $this->normalizeAssocArray($value);
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $this->normalizeAssocArray($decoded) : [];
    }

    private function nullableDateTime(mixed $value): ?CarbonImmutable
    {
        return $value === null ? null : $this->dateTime($value);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<string, mixed>
     */
    private function normalizeAssocArray(array $value): array
    {
        $normalized = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function toData(stdClass $row): MyIdVerificationData
    {
        return new MyIdVerificationData(
            id: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            webhookId: $this->stringValue($row->webhook_id ?? null),
            externalReference: $this->stringValue($row->external_reference ?? null),
            providerReference: $this->stringValue($row->provider_reference ?? null),
            status: $this->stringValue($row->status ?? null),
            subject: $this->jsonArray($row->subject ?? null),
            metadata: $this->jsonArray($row->metadata ?? null),
            resultPayload: $this->jsonArray($row->result_payload ?? null),
            completedAt: $this->nullableDateTime($row->completed_at ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }
}
