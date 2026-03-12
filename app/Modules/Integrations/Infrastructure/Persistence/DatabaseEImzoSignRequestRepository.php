<?php

namespace App\Modules\Integrations\Infrastructure\Persistence;

use App\Modules\Integrations\Application\Contracts\EImzoSignRequestRepository;
use App\Modules\Integrations\Application\Data\EImzoSignRequestData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseEImzoSignRequestRepository implements EImzoSignRequestRepository
{
    #[\Override]
    public function create(
        string $tenantId,
        string $webhookId,
        string $externalReference,
        string $providerReference,
        string $documentHash,
        string $documentName,
        array $signer,
        array $metadata,
        CarbonImmutable $now,
    ): EImzoSignRequestData {
        $id = (string) Str::uuid();

        DB::table('eimzo_sign_requests')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'webhook_id' => $webhookId,
            'external_reference' => $externalReference,
            'provider_reference' => $providerReference,
            'status' => 'pending',
            'document_hash' => $documentHash,
            'document_name' => $documentName,
            'signer' => json_encode($signer, JSON_THROW_ON_ERROR),
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'signature_payload' => null,
            'completed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findByProviderReference($tenantId, $providerReference)
            ?? throw new \LogicException('Created E-IMZO sign request could not be reloaded.');
    }

    #[\Override]
    public function findByProviderReference(string $tenantId, string $providerReference): ?EImzoSignRequestData
    {
        $row = DB::table('eimzo_sign_requests')
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
        array $signaturePayload,
        CarbonImmutable $completedAt,
        CarbonImmutable $updatedAt,
    ): ?EImzoSignRequestData {
        $updated = DB::table('eimzo_sign_requests')
            ->where('tenant_id', $tenantId)
            ->where('provider_reference', $providerReference)
            ->where('status', 'pending')
            ->update([
                'status' => $status,
                'signature_payload' => json_encode($signaturePayload, JSON_THROW_ON_ERROR),
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

    private function toData(stdClass $row): EImzoSignRequestData
    {
        return new EImzoSignRequestData(
            id: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            webhookId: $this->stringValue($row->webhook_id ?? null),
            externalReference: $this->stringValue($row->external_reference ?? null),
            providerReference: $this->stringValue($row->provider_reference ?? null),
            status: $this->stringValue($row->status ?? null),
            documentHash: $this->stringValue($row->document_hash ?? null),
            documentName: $this->stringValue($row->document_name ?? null),
            signer: $this->jsonArray($row->signer ?? null),
            metadata: $this->jsonArray($row->metadata ?? null),
            signaturePayload: $this->jsonArray($row->signature_payload ?? null),
            completedAt: $this->nullableDateTime($row->completed_at ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }
}
