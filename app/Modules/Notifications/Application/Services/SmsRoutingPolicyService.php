<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Application\Contracts\SmsProviderRegistry;
use App\Modules\Notifications\Application\Contracts\SmsRoutingRepository;
use App\Modules\Notifications\Application\Data\SmsRoutingRuleData;
use App\Modules\Notifications\Application\Data\SmsRoutingSettingsData;
use App\Modules\Notifications\Domain\SmsMessageType;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class SmsRoutingPolicyService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SmsRoutingRepository $smsRoutingRepository,
        private readonly SmsProviderRegistry $smsProviderRegistry,
    ) {}

    public function list(): SmsRoutingSettingsData
    {
        return $this->listForTenant($this->tenantContext->requireTenantId());
    }

    public function listForTenant(string $tenantId): SmsRoutingSettingsData
    {
        $customRoutes = [];

        foreach ($this->smsRoutingRepository->listForTenant($tenantId) as $route) {
            $customRoutes[$route->messageType] = $route;
        }

        $routes = [];

        foreach (SmsMessageType::all() as $messageType) {
            $routes[] = $customRoutes[$messageType] ?? new SmsRoutingRuleData(
                tenantId: $tenantId,
                messageType: $messageType,
                providers: $this->defaultProviders($messageType),
                source: 'default',
            );
        }

        return new SmsRoutingSettingsData(
            providers: $this->smsProviderRegistry->configuredProviders(),
            routes: $routes,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $routes
     */
    public function update(array $routes): SmsRoutingSettingsData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $seenMessageTypes = [];

        foreach ($routes as $route) {
            $messageType = $this->messageType($route['message_type'] ?? null);

            if (in_array($messageType, $seenMessageTypes, true)) {
                throw new UnprocessableEntityHttpException('Each SMS message_type may be provided only once.');
            }

            $seenMessageTypes[] = $messageType;
            $providers = $this->providers($route['providers'] ?? null);
            $this->smsRoutingRepository->upsert($tenantId, $messageType, $providers);
        }

        return $this->listForTenant($tenantId);
    }

    /**
     * @return list<string>
     */
    public function providersForTenant(string $tenantId, string $messageType): array
    {
        $route = $this->smsRoutingRepository->findForTenantAndMessageType($tenantId, $messageType);

        return $route instanceof SmsRoutingRuleData
            ? $route->providers
            : $this->defaultProviders($messageType);
    }

    /**
     * @return list<string>
     */
    private function defaultProviders(string $messageType): array
    {
        /** @var mixed $providers */
        $providers = config('notifications.sms.default_routing.'.$messageType, []);

        if (! is_array($providers)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                static fn (mixed $provider): ?string => is_string($provider) && trim($provider) !== '' ? trim($provider) : null,
                $providers,
            ),
            static fn (?string $provider): bool => $provider !== null,
        ));
    }

    private function messageType(mixed $value): string
    {
        if (! is_string($value)) {
            throw new UnprocessableEntityHttpException('The routes.*.message_type field is required.');
        }

        $normalized = strtolower(trim($value));

        if (! in_array($normalized, SmsMessageType::all(), true)) {
            throw new UnprocessableEntityHttpException('The routes.*.message_type field must be one of: otp, reminder, transactional, bulk.');
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function providers(mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            throw new UnprocessableEntityHttpException('The routes.*.providers field must be a non-empty array.');
        }

        $configuredProviders = array_column($this->smsProviderRegistry->configuredProviders(), 'key');
        $providers = [];

        foreach ($value as $provider) {
            if (! is_string($provider) || trim($provider) === '') {
                throw new UnprocessableEntityHttpException('The routes.*.providers field must contain provider keys.');
            }

            $normalized = strtolower(trim($provider));

            if (! in_array($normalized, $configuredProviders, true)) {
                throw new UnprocessableEntityHttpException('The routes.*.providers field references an unknown SMS provider.');
            }

            if (in_array($normalized, $providers, true)) {
                throw new UnprocessableEntityHttpException('The routes.*.providers field may not contain duplicates.');
            }

            $providers[] = $normalized;
        }

        return $providers;
    }
}
