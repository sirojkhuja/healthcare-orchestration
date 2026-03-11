<?php

namespace App\Modules\Notifications\Application\Data;

final readonly class SmsRoutingSettingsData
{
    /**
     * @param  list<array{key: string, name: string}>  $providers
     * @param  list<SmsRoutingRuleData>  $routes
     */
    public function __construct(
        public array $providers,
        public array $routes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'providers' => $this->providers,
            'routes' => array_map(
                static fn (SmsRoutingRuleData $route): array => $route->toArray(),
                $this->routes,
            ),
        ];
    }
}
