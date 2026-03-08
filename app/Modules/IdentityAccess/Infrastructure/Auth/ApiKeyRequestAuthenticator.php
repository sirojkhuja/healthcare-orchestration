<?php

namespace App\Modules\IdentityAccess\Infrastructure\Auth;

use App\Models\User;
use App\Modules\IdentityAccess\Application\Contracts\ApiKeyRepository;
use App\Modules\IdentityAccess\Application\Contracts\TenantIpAllowlistRepository;
use App\Modules\IdentityAccess\Application\Exceptions\IpAddressNotAllowedException;
use App\Modules\IdentityAccess\Application\Exceptions\RevokedApiKeyException;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ApiKeyRequestAuthenticator
{
    public function __construct(
        private readonly ApiKeyRepository $apiKeyRepository,
        private readonly TenantIpAllowlistRepository $tenantIpAllowlistRepository,
        private readonly TenantContext $tenantContext,
    ) {}

    public function authenticate(Request $request): ?Authenticatable
    {
        $presentedKey = $this->presentedKey($request);

        if ($presentedKey === null) {
            return null;
        }

        $keyId = $presentedKey['key_id'];
        $record = $this->apiKeyRepository->findById($keyId);

        if ($record === null) {
            return null;
        }

        $tokenHash = hash('sha256', $presentedKey['presented']);

        if (! hash_equals($record->tokenHash, $tokenHash)) {
            return null;
        }

        if ($record->revokedAt !== null) {
            throw new RevokedApiKeyException;
        }

        $now = CarbonImmutable::now();

        if ($record->expiresAt !== null && $record->expiresAt->lessThanOrEqualTo($now)) {
            return null;
        }

        $tenantId = $this->tenantContext->tenantId();

        if (is_string($tenantId) && $tenantId !== '' && ! $this->tenantIpAllowlistRepository->allows($tenantId, $request->ip())) {
            throw new IpAddressNotAllowedException;
        }

        $user = User::query()->find($record->userId);

        if (! $user instanceof User) {
            return null;
        }

        $this->apiKeyRepository->touchUsage($record->keyId, $now);
        $request->attributes->set('auth_api_key_id', $record->keyId);

        return $user;
    }

    /**
     * @return array{key_id: string, presented: string}|null
     */
    private function presentedKey(Request $request): ?array
    {
        $resolvedHeaderName = config()->string('medflow.auth.api_keys.header', 'X-API-Key');
        $resolvedPrefix = config()->string('medflow.auth.api_keys.prefix', 'mfk');
        $presented = $request->header($resolvedHeaderName);

        if (! is_string($presented) || $presented === '') {
            return null;
        }

        $pattern = '/^'.preg_quote($resolvedPrefix, '/').'_([a-f0-9-]{36})\.[A-Za-z0-9]+$/';

        if (! preg_match($pattern, $presented, $matches)) {
            return null;
        }

        $matchedKeyId = $matches[1] ?? null;

        if (! is_string($matchedKeyId) || ! Str::isUuid($matchedKeyId)) {
            return null;
        }

        /** @var non-empty-string $matchedKeyId */
        $keyId = strtolower($matchedKeyId);

        return [
            'key_id' => $keyId,
            'presented' => $presented,
        ];
    }
}
