<?php

namespace App\Modules\IdentityAccess\Application\Data;

final readonly class MfaSetupData
{
    /**
     * @param  list<string>  $recoveryCodes
     */
    public function __construct(
        public string $credentialId,
        public string $secret,
        public string $otpauthUri,
        public array $recoveryCodes,
    ) {}

    /**
     * @return array{
     *     status: string,
     *     credential: array{id: string},
     *     secret: string,
     *     otpauth_uri: string,
     *     recovery_codes: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'status' => 'mfa_setup_pending',
            'credential' => [
                'id' => $this->credentialId,
            ],
            'secret' => $this->secret,
            'otpauth_uri' => $this->otpauthUri,
            'recovery_codes' => $this->recoveryCodes,
        ];
    }
}
