<?php

namespace App\Modules\IdentityAccess\Application\Data;

final readonly class CreatedApiKeyData
{
    public function __construct(
        public ApiKeyViewData $apiKey,
        public string $plainTextKey,
    ) {}

    /**
     * @return array{
     *     api_key: array{
     *         id: string,
     *         name: string,
     *         prefix: string,
     *         last_used_at: string|null,
     *         expires_at: string|null,
     *         revoked_at: string|null,
     *         created_at: string
     *     },
     *     plaintext_key: string
     * }
     */
    public function toArray(): array
    {
        return [
            'api_key' => $this->apiKey->toArray(),
            'plaintext_key' => $this->plainTextKey,
        ];
    }
}
