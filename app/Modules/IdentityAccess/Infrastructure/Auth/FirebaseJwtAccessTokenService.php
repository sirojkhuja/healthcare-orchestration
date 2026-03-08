<?php

namespace App\Modules\IdentityAccess\Infrastructure\Auth;

use App\Modules\IdentityAccess\Application\Contracts\AccessTokenService;
use App\Modules\IdentityAccess\Application\Data\AccessTokenPayload;
use App\Modules\IdentityAccess\Application\Data\AuthenticatedUserData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use LogicException;
use stdClass;
use UnexpectedValueException;

final class FirebaseJwtAccessTokenService implements AccessTokenService
{
    #[\Override]
    public function decode(string $token): AccessTokenPayload
    {
        $payload = JWT::decode($token, new Key($this->secret(), $this->algorithm()));
        $claims = $this->claims($payload);
        /** @var mixed $issuer */
        $issuer = $claims['iss'] ?? null;
        /** @var mixed $audience */
        $audience = $claims['aud'] ?? null;

        if ($issuer !== $this->issuer() || $audience !== $this->audience()) {
            throw new UnexpectedValueException('JWT issuer or audience is invalid.');
        }

        return new AccessTokenPayload(
            userId: $this->stringClaim($claims, 'sub'),
            sessionId: $this->stringClaim($claims, 'sid'),
            accessTokenId: $this->stringClaim($claims, 'jti'),
            issuedAt: CarbonImmutable::createFromTimestampUTC($this->intClaim($claims, 'iat')),
            expiresAt: CarbonImmutable::createFromTimestampUTC($this->intClaim($claims, 'exp')),
        );
    }

    #[\Override]
    public function issue(
        AuthenticatedUserData $user,
        string $sessionId,
        string $accessTokenId,
        DateTimeInterface $issuedAt,
        DateTimeInterface $expiresAt,
    ): string {
        return JWT::encode([
            'iss' => $this->issuer(),
            'aud' => $this->audience(),
            'iat' => $issuedAt->getTimestamp(),
            'nbf' => $issuedAt->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
            'sub' => $user->id,
            'sid' => $sessionId,
            'jti' => $accessTokenId,
        ], $this->secret(), $this->algorithm());
    }

    /**
     * @return array<string, mixed>
     */
    private function claims(stdClass $payload): array
    {
        /** @var array<string, mixed> $claims */
        $claims = (array) $payload;

        return $claims;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function intClaim(array $claims, string $key): int
    {
        /** @var mixed $claim */
        $claim = $claims[$key] ?? null;

        return is_numeric($claim) ? (int) $claim : 0;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function stringClaim(array $claims, string $key): string
    {
        /** @var mixed $claim */
        $claim = $claims[$key] ?? null;

        return is_string($claim) ? $claim : '';
    }

    private function algorithm(): string
    {
        return config()->string('medflow.auth.jwt.algorithm', 'HS256');
    }

    private function audience(): string
    {
        return config()->string('medflow.auth.jwt.audience', 'medflow-api');
    }

    private function issuer(): string
    {
        return config()->string('medflow.auth.jwt.issuer', 'medflow');
    }

    private function secret(): string
    {
        $secret = config()->string('medflow.auth.jwt.secret', '');

        if ($secret !== '') {
            return $secret;
        }

        $appKey = config()->string('app.key');

        if ($appKey === '') {
            throw new LogicException('An APP_KEY or AUTH_JWT_SECRET value is required for JWT signing.');
        }

        if (! str_starts_with($appKey, 'base64:')) {
            return $appKey;
        }

        $decoded = base64_decode(substr($appKey, 7), true);

        if (! is_string($decoded) || $decoded === '') {
            throw new LogicException('The configured APP_KEY is invalid for JWT signing.');
        }

        return $decoded;
    }
}
