<?php

namespace App\Modules\IdentityAccess\Infrastructure\Auth\Persistence;

use App\Modules\IdentityAccess\Application\Contracts\ApiKeyRepository;
use App\Modules\IdentityAccess\Application\Data\ApiKeyData;
use Carbon\CarbonImmutable;
use DateTimeInterface;

final class DatabaseApiKeyRepository implements ApiKeyRepository
{
    #[\Override]
    public function create(
        string $keyId,
        string $userId,
        string $name,
        string $prefix,
        string $tokenHash,
        ?DateTimeInterface $expiresAt,
    ): ApiKeyData {
        $record = ApiKeyRecord::query()->create([
            'id' => $keyId,
            'user_id' => $userId,
            'name' => $name,
            'key_prefix' => $prefix,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);

        return $this->toData($record);
    }

    #[\Override]
    public function findById(string $keyId): ?ApiKeyData
    {
        $record = ApiKeyRecord::query()->find($keyId);

        return $record instanceof ApiKeyRecord ? $this->toData($record) : null;
    }

    #[\Override]
    public function listForUser(string $userId): array
    {
        /** @var list<ApiKeyRecord> $records */
        $records = ApiKeyRecord::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->all();

        return array_map($this->toData(...), $records);
    }

    #[\Override]
    public function revokeForUser(string $keyId, string $userId, DateTimeInterface $revokedAt): bool
    {
        $updated = ApiKeyRecord::query()
            ->whereKey($keyId)
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => $revokedAt,
                'updated_at' => CarbonImmutable::now(),
            ]);

        return $updated > 0;
    }

    #[\Override]
    public function touchUsage(string $keyId, DateTimeInterface $usedAt): void
    {
        ApiKeyRecord::query()
            ->whereKey($keyId)
            ->update([
                'last_used_at' => $usedAt,
                'updated_at' => CarbonImmutable::now(),
            ]);
    }

    private function toData(ApiKeyRecord $record): ApiKeyData
    {
        return new ApiKeyData(
            keyId: $record->id,
            userId: $record->user_id,
            name: $record->name,
            prefix: $record->key_prefix,
            tokenHash: $record->token_hash,
            lastUsedAt: $record->last_used_at,
            expiresAt: $record->expires_at,
            revokedAt: $record->revoked_at,
            createdAt: $record->created_at,
        );
    }
}
