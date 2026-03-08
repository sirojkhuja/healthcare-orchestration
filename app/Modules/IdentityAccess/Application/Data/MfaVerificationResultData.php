<?php

namespace App\Modules\IdentityAccess\Application\Data;

final readonly class MfaVerificationResultData
{
    private function __construct(
        private ?AuthenticatedSessionData $session = null,
        private ?string $status = null,
    ) {}

    public static function loginCompleted(AuthenticatedSessionData $session): self
    {
        return new self(session: $session);
    }

    public static function setupCompleted(): self
    {
        return new self(status: 'mfa_enabled');
    }

    public function usesSessionResponse(): bool
    {
        return $this->session !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->session instanceof AuthenticatedSessionData) {
            return $this->session->toArray();
        }

        return [
            'status' => $this->status ?? 'mfa_verified',
        ];
    }
}
