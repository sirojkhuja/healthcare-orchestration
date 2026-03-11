<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\Notifications\Application\Services\SmsDiagnosticSendService;

final class IntegrationSmsDispatchService
{
    public function __construct(
        private readonly SmsDiagnosticSendService $smsDiagnosticSendService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function send(string $providerKey, array $attributes): array
    {
        return $this->smsDiagnosticSendService->send($attributes, $providerKey);
    }
}
