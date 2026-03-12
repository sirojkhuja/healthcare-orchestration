<?php

namespace App\Modules\Provider\Application\Services;

use App\Modules\Provider\Application\Contracts\ProviderRepository;
use App\Modules\Provider\Application\Data\ProviderData;
use App\Modules\Provider\Application\Data\ProviderSearchCriteria;
use App\Shared\Application\Contracts\TenantContext;

final class ProviderReadService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ProviderRepository $providerRepository,
    ) {}

    /**
     * @return list<ProviderData>
     */
    public function search(ProviderSearchCriteria $criteria): array
    {
        $providers = array_values(array_filter(
            $this->providerRepository->listForTenant($this->tenantContext->requireTenantId()),
            fn (ProviderData $provider): bool => $this->matchesFilters($provider, $criteria),
        ));

        if ($criteria->hasQuery()) {
            usort($providers, fn (ProviderData $left, ProviderData $right): int => $this->compare($left, $right, $criteria));
        }

        return array_slice($providers, 0, $criteria->limit);
    }

    private function compare(ProviderData $left, ProviderData $right, ProviderSearchCriteria $criteria): int
    {
        $scoreDiff = $this->score($right, $criteria) <=> $this->score($left, $criteria);

        if ($scoreDiff !== 0) {
            return $scoreDiff;
        }

        return [
            $left->lastName,
            $left->firstName,
            $left->createdAt->toIso8601String(),
        ] <=> [
            $right->lastName,
            $right->firstName,
            $right->createdAt->toIso8601String(),
        ];
    }

    private function matchesFilters(ProviderData $provider, ProviderSearchCriteria $criteria): bool
    {
        if ($criteria->providerType !== null && $provider->providerType !== $criteria->providerType) {
            return false;
        }

        if ($criteria->clinicId !== null && $provider->clinicId !== $criteria->clinicId) {
            return false;
        }

        if ($criteria->hasEmail !== null && ($provider->email !== null) !== $criteria->hasEmail) {
            return false;
        }

        if ($criteria->hasPhone !== null && ($provider->phone !== null) !== $criteria->hasPhone) {
            return false;
        }

        $query = $criteria->normalizedQuery();

        if ($query === null) {
            return true;
        }

        return $this->score($provider, $criteria) > 0;
    }

    private function score(ProviderData $provider, ProviderSearchCriteria $criteria): int
    {
        $query = $criteria->normalizedQuery();

        if ($query === null) {
            return 0;
        }

        $score = 0;

        foreach ([
            $provider->firstName,
            $provider->lastName,
            $provider->preferredName,
            $provider->email,
            $provider->phone,
            $provider->providerType,
        ] as $field) {
            $score += $this->fieldScore($field, $query, 140, 100, 60);
        }

        foreach ($criteria->tokens() as $token) {
            foreach ([
                $provider->firstName,
                $provider->lastName,
                $provider->preferredName,
                $provider->email,
                $provider->phone,
                $provider->providerType,
            ] as $field) {
                $score += $this->fieldScore($field, $token, 30, 20, 10);
            }
        }

        return $score;
    }

    private function fieldScore(?string $field, string $query, int $exact, int $prefix, int $contains): int
    {
        if (! is_string($field) || trim($field) === '') {
            return 0;
        }

        $normalizedField = mb_strtolower(trim($field));

        if ($normalizedField === $query) {
            return $exact;
        }

        if (str_starts_with($normalizedField, $query)) {
            return $prefix;
        }

        return str_contains($normalizedField, $query) ? $contains : 0;
    }
}
