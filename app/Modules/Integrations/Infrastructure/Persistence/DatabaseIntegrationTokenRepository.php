<?php

namespace App\Modules\Integrations\Infrastructure\Persistence;

use App\Modules\Integrations\Application\Contracts\IntegrationTokenRepository;
use App\Modules\Integrations\Application\Data\IntegrationTokenData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseIntegrationTokenRepository implements IntegrationTokenRepository
{
    #[\Override]
    public function list(string $tenantId, string $integrationKey): array
    {
        return array_values(array_map(
            fn (stdClass $row): IntegrationTokenData => $this->toData($row),
            DB::table('integration_tokens')
                ->where('tenant_id', $tenantId)
                ->where('integration_key', $integrationKey)
                ->orderByDesc('created_at')
                ->get()
                ->all(),
        ));
    }

    #[\Override]
    public function materializeFromCredentials(
        string $tenantId,
        string $integrationKey,
        array $credentialValues,
        CarbonImmutable $now,
    ): ?IntegrationTokenData {
        $accessToken = $this->normalizedToken($credentialValues['access_token'] ?? null);
        $refreshToken = $this->normalizedToken($credentialValues['refresh_token'] ?? null);

        if ($accessToken === null && $refreshToken === null) {
            return null;
        }

        $id = (string) Str::uuid();

        DB::table('integration_tokens')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'integration_key' => $integrationKey,
            'label' => 'primary',
            'access_token' => $accessToken !== null ? Crypt::encryptString($accessToken) : null,
            'refresh_token' => $refreshToken !== null ? Crypt::encryptString($refreshToken) : null,
            'token_type' => $this->normalizedString($credentialValues['token_type'] ?? null) ?? 'Bearer',
            'scopes' => json_encode($this->normalizedScopes($credentialValues['scopes'] ?? null), JSON_THROW_ON_ERROR),
            'access_token_expires_at' => $this->nullableDateTime($credentialValues['access_token_expires_at'] ?? null),
            'refresh_token_expires_at' => $this->nullableDateTime($credentialValues['refresh_token_expires_at'] ?? null),
            'last_refreshed_at' => $accessToken !== null ? $now : null,
            'revoked_at' => null,
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $integrationKey, $id);
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $integrationKey, string $tokenId): ?IntegrationTokenData
    {
        $row = DB::table('integration_tokens')
            ->where('tenant_id', $tenantId)
            ->where('integration_key', $integrationKey)
            ->where('id', $tokenId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function latestActive(string $tenantId, string $integrationKey): ?IntegrationTokenData
    {
        $row = DB::table('integration_tokens')
            ->where('tenant_id', $tenantId)
            ->where('integration_key', $integrationKey)
            ->whereNull('revoked_at')
            ->orderByDesc('created_at')
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function refresh(
        string $tenantId,
        string $integrationKey,
        string $tokenId,
        string $accessToken,
        ?CarbonImmutable $accessTokenExpiresAt,
        CarbonImmutable $refreshedAt,
    ): ?IntegrationTokenData {
        $updated = DB::table('integration_tokens')
            ->where('tenant_id', $tenantId)
            ->where('integration_key', $integrationKey)
            ->where('id', $tokenId)
            ->whereNull('revoked_at')
            ->update([
                'access_token' => Crypt::encryptString($accessToken),
                'access_token_expires_at' => $accessTokenExpiresAt,
                'last_refreshed_at' => $refreshedAt,
                'updated_at' => $refreshedAt,
            ]);

        return $updated > 0 ? $this->findInTenant($tenantId, $integrationKey, $tokenId) : null;
    }

    #[\Override]
    public function revoke(
        string $tenantId,
        string $integrationKey,
        string $tokenId,
        CarbonImmutable $revokedAt,
    ): ?IntegrationTokenData {
        $updated = DB::table('integration_tokens')
            ->where('tenant_id', $tenantId)
            ->where('integration_key', $integrationKey)
            ->where('id', $tokenId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => $revokedAt,
                'updated_at' => $revokedAt,
            ]);

        return $updated > 0 ? $this->findInTenant($tenantId, $integrationKey, $tokenId) : null;
    }

    #[\Override]
    public function revokeAll(string $tenantId, string $integrationKey, CarbonImmutable $revokedAt): int
    {
        return DB::table('integration_tokens')
            ->where('tenant_id', $tenantId)
            ->where('integration_key', $integrationKey)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => $revokedAt,
                'updated_at' => $revokedAt,
            ]);
    }

    private function decryptPreview(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $decrypted = Crypt::decryptString($value);

        return '****'.substr($decrypted, -4);
    }

    /**
     * @return list<string>
     */
    private function normalizedScopes(mixed $value): array
    {
        if (is_array($value)) {
            $scopes = [];

            foreach (array_keys($value) as $index) {
                if (! is_string($value[$index] ?? null)) {
                    continue;
                }

                /** @var string $scope */
                $scope = $value[$index];
                $scope = trim($scope);

                if ($scope !== '') {
                    $scopes[] = $scope;
                }
            }

            return array_values(array_unique($scopes));
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $parts = preg_split('/[\s,]+/', trim($value)) ?: [];

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    private function normalizedString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function normalizedToken(mixed $value): ?string
    {
        return $this->normalizedString($value);
    }

    private function nullableDateTime(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? CarbonImmutable::parse($normalized) : null;
    }

    private function toData(stdClass $row): IntegrationTokenData
    {
        $scopes = $this->decodedArray($row->scopes ?? null);
        $metadata = $this->decodedArray($row->metadata ?? null);

        return new IntegrationTokenData(
            id: $this->stringValue($row->id ?? null),
            integrationKey: $this->stringValue($row->integration_key ?? null),
            label: $this->stringValue($row->label ?? null),
            tokenType: $this->stringValue($row->token_type ?? null) !== '' ? $this->stringValue($row->token_type ?? null) : 'Bearer',
            scopes: $this->normalizedScopes($scopes),
            accessTokenPreview: $this->decryptPreview($row->access_token ?? null),
            refreshTokenPreview: $this->decryptPreview($row->refresh_token ?? null),
            accessTokenExpiresAt: $this->nullableDateTime($row->access_token_expires_at ?? null),
            refreshTokenExpiresAt: $this->nullableDateTime($row->refresh_token_expires_at ?? null),
            lastRefreshedAt: $this->nullableDateTime($row->last_refreshed_at ?? null),
            revokedAt: $this->nullableDateTime($row->revoked_at ?? null),
            metadata: $this->metadata($metadata),
            createdAt: $this->nullableDateTime($row->created_at ?? null) ?? CarbonImmutable::now(),
            updatedAt: $this->nullableDateTime($row->updated_at ?? null) ?? CarbonImmutable::now(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $metadata */
        $metadata = array_filter(
            $value,
            static fn (mixed $_item, mixed $key): bool => is_string($key),
            ARRAY_FILTER_USE_BOTH,
        );

        return $metadata;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decodedArray(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
