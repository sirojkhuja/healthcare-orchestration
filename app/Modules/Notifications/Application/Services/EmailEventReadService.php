<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Application\Contracts\EmailEventRepository;
use App\Modules\Notifications\Application\Data\EmailEventData;
use App\Modules\Notifications\Application\Data\EmailEventListCriteria;
use App\Shared\Application\Contracts\TenantContext;

final class EmailEventReadService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EmailEventRepository $emailEventRepository,
    ) {}

    /**
     * @return list<EmailEventData>
     */
    public function list(EmailEventListCriteria $criteria): array
    {
        return $this->emailEventRepository->search($this->tenantContext->requireTenantId(), $criteria);
    }
}
