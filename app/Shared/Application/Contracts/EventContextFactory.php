<?php

namespace App\Shared\Application\Contracts;

interface EventContextFactory
{
    /**
     * @return array{request_id: string, correlation_id: string, causation_id: string, tenant_id?: string}
     */
    public function make(?string $causationId = null): array;
}
