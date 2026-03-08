<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\IdentityAccess\Application\Contracts\AuthSessionRepository;
use App\Modules\IdentityAccess\Application\Data\AuthSessionViewData;
use App\Modules\IdentityAccess\Application\Queries\ListSessionsQuery;

final class ListSessionsQueryHandler
{
    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
        private readonly AuthSessionRepository $authSessionRepository,
    ) {}

    /**
     * @return list<AuthSessionViewData>
     */
    public function handle(ListSessionsQuery $query): array
    {
        $current = $this->authenticatedRequestContext->current();

        return $this->authSessionRepository->listForUser($current->user->id, $current->sessionId);
    }
}
