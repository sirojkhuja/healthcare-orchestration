<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Contracts\ApiKeyRepository;
use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\IdentityAccess\Application\Data\ApiKeyViewData;
use App\Modules\IdentityAccess\Application\Queries\ListApiKeysQuery;

final class ListApiKeysQueryHandler
{
    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
        private readonly ApiKeyRepository $apiKeyRepository,
    ) {}

    /**
     * @return list<ApiKeyViewData>
     */
    public function handle(ListApiKeysQuery $query): array
    {
        $current = $this->authenticatedRequestContext->current();

        return array_map(
            static fn ($apiKey): ApiKeyViewData => $apiKey->toView(),
            $this->apiKeyRepository->listForUser($current->user->id),
        );
    }
}
