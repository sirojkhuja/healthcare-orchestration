<?php

namespace App\Modules\IdentityAccess\Infrastructure\Auth\Persistence;

use App\Modules\IdentityAccess\Application\Contracts\AuthSessionRepository;
use App\Modules\IdentityAccess\Application\Data\AuthSessionData;
use App\Modules\IdentityAccess\Application\Data\AuthSessionViewData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class DatabaseAuthSessionRepository implements AuthSessionRepository
{
    #[\Override]
    public function create(
        string $userId,
        string $refreshToken,
        DateTimeInterface $accessTokenExpiresAt,
        DateTimeInterface $refreshTokenExpiresAt,
        ?string $ipAddress,
        ?string $userAgent,
    ): AuthSessionData {
        $record = AuthSessionRecord::query()->create([
            'user_id' => $userId,
            'access_token_id' => Str::uuid()->toString(),
            'refresh_token_hash' => $this->refreshTokenHash($refreshToken),
            'access_token_expires_at' => $accessTokenExpiresAt,
            'refresh_token_expires_at' => $refreshTokenExpiresAt,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'last_used_at' => null,
            'revoked_at' => null,
        ]);

        return $this->toData($record);
    }

    #[\Override]
    public function findActiveByAccessToken(string $sessionId, string $accessTokenId, DateTimeInterface $now): ?AuthSessionData
    {
        $record = $this->activeQuery()
            ->whereKey($sessionId)
            ->where('access_token_id', $accessTokenId)
            ->where('access_token_expires_at', '>', $now)
            ->first();

        return $record instanceof AuthSessionRecord ? $this->toData($record) : null;
    }

    #[\Override]
    public function findActiveByRefreshToken(string $refreshToken, DateTimeInterface $now): ?AuthSessionData
    {
        $record = $this->activeQuery()
            ->where('refresh_token_hash', $this->refreshTokenHash($refreshToken))
            ->where('refresh_token_expires_at', '>', $now)
            ->first();

        return $record instanceof AuthSessionRecord ? $this->toData($record) : null;
    }

    #[\Override]
    public function listForUser(string $userId, string $currentSessionId): array
    {
        /** @var list<AuthSessionRecord> $records */
        $records = AuthSessionRecord::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->all();

        $sessions = array_map(
            fn (AuthSessionRecord $record): AuthSessionViewData => new AuthSessionViewData(
                id: $record->id,
                current: $record->id === $currentSessionId,
                ipAddress: $record->ip_address,
                userAgent: $record->user_agent,
                accessTokenExpiresAt: $record->access_token_expires_at,
                refreshTokenExpiresAt: $record->refresh_token_expires_at,
                lastUsedAt: $record->last_used_at,
                revokedAt: $record->revoked_at,
                createdAt: $record->created_at,
            ),
            $records,
        );

        usort($sessions, function (AuthSessionViewData $left, AuthSessionViewData $right): int {
            if ($left->current === $right->current) {
                return strcmp($right->createdAt->format(DATE_ATOM), $left->createdAt->format(DATE_ATOM));
            }

            return $left->current ? -1 : 1;
        });

        return $sessions;
    }

    #[\Override]
    public function revoke(string $sessionId, DateTimeInterface $revokedAt): void
    {
        AuthSessionRecord::query()
            ->whereKey($sessionId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => $revokedAt,
                'updated_at' => CarbonImmutable::now(),
            ]);
    }

    #[\Override]
    public function revokeAllForUser(string $userId, DateTimeInterface $revokedAt): int
    {
        return AuthSessionRecord::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => $revokedAt,
                'updated_at' => CarbonImmutable::now(),
            ]);
    }

    #[\Override]
    public function revokeForUser(string $sessionId, string $userId, DateTimeInterface $revokedAt): bool
    {
        $updated = AuthSessionRecord::query()
            ->whereKey($sessionId)
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => $revokedAt,
                'updated_at' => CarbonImmutable::now(),
            ]);

        return $updated > 0;
    }

    #[\Override]
    public function rotateRefreshToken(
        string $sessionId,
        string $refreshToken,
        DateTimeInterface $accessTokenExpiresAt,
        DateTimeInterface $refreshTokenExpiresAt,
        ?string $ipAddress,
        ?string $userAgent,
        DateTimeInterface $usedAt,
    ): ?AuthSessionData {
        $updated = AuthSessionRecord::query()
            ->whereKey($sessionId)
            ->whereNull('revoked_at')
            ->update([
                'access_token_id' => Str::uuid()->toString(),
                'refresh_token_hash' => $this->refreshTokenHash($refreshToken),
                'access_token_expires_at' => $accessTokenExpiresAt,
                'refresh_token_expires_at' => $refreshTokenExpiresAt,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'last_used_at' => $usedAt,
                'updated_at' => CarbonImmutable::now(),
            ]);

        if ($updated < 1) {
            return null;
        }

        $record = AuthSessionRecord::query()->find($sessionId);

        return $record instanceof AuthSessionRecord ? $this->toData($record) : null;
    }

    #[\Override]
    public function touchUsage(string $sessionId, DateTimeInterface $usedAt): void
    {
        AuthSessionRecord::query()
            ->whereKey($sessionId)
            ->whereNull('revoked_at')
            ->update([
                'last_used_at' => $usedAt,
                'updated_at' => CarbonImmutable::now(),
            ]);
    }

    /**
     * @return Builder<AuthSessionRecord>
     */
    private function activeQuery(): Builder
    {
        /** @var Builder<AuthSessionRecord> $query */
        $query = AuthSessionRecord::query();
        $query->whereNull('revoked_at');

        return $query;
    }

    private function refreshTokenHash(string $refreshToken): string
    {
        return hash('sha256', $refreshToken);
    }

    private function toData(AuthSessionRecord $record): AuthSessionData
    {
        return new AuthSessionData(
            sessionId: $record->id,
            userId: $record->user_id,
            accessTokenId: $record->access_token_id,
            accessTokenExpiresAt: $record->access_token_expires_at,
            refreshTokenExpiresAt: $record->refresh_token_expires_at,
            lastUsedAt: $record->last_used_at,
            revokedAt: $record->revoked_at,
        );
    }
}
