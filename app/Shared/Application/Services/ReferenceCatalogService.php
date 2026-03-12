<?php

namespace App\Shared\Application\Services;

use App\Shared\Application\Contracts\ReferenceCatalogRepository;
use App\Shared\Application\Contracts\TenantCache;
use App\Shared\Application\Data\ReferenceEntryData;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ReferenceCatalogService
{
    private const SUPPORTED_CATALOGS = [
        'currencies',
        'countries',
        'languages',
        'diagnosis_codes',
        'procedure_codes',
        'insurance_codes',
    ];

    public function __construct(
        private readonly ReferenceCatalogRepository $referenceCatalogRepository,
        private readonly TenantCache $tenantCache,
    ) {}

    /**
     * @return list<ReferenceEntryData>
     */
    public function list(string $catalog, ?string $query, int $limit): array
    {
        $normalizedCatalog = $this->normalizedCatalog($catalog);
        $normalizedQuery = $this->normalizedQuery($query);
        $normalizedLimit = $this->normalizedLimit($limit);

        /** @var list<ReferenceEntryData> $entries */
        $entries = $this->tenantCache->remember(
            'reference-data',
            [$normalizedCatalog, $normalizedQuery ?? '', $normalizedLimit],
            null,
            3600,
            fn (): array => $this->queryCatalog($normalizedCatalog, $normalizedQuery, $normalizedLimit),
        );

        return $entries;
    }

    private function normalizedCatalog(string $catalog): string
    {
        $normalized = mb_strtolower(trim($catalog));

        if (! in_array($normalized, self::SUPPORTED_CATALOGS, true)) {
            throw new UnprocessableEntityHttpException('The requested reference catalog is not supported.');
        }

        return $normalized;
    }

    private function normalizedLimit(int $limit): int
    {
        if ($limit < 1 || $limit > 100) {
            throw new UnprocessableEntityHttpException('Reference catalog limit must be between 1 and 100.');
        }

        return $limit;
    }

    /**
     * @return list<ReferenceEntryData>
     */
    private function queryCatalog(string $catalog, ?string $query, int $limit): array
    {
        $results = [];

        foreach ($this->referenceCatalogRepository->list($catalog) as $entry) {
            if ($query !== null && ! $this->matches($entry, $query)) {
                continue;
            }

            $results[] = $entry;
        }

        usort($results, static fn (ReferenceEntryData $left, ReferenceEntryData $right): int => [
            $left->name,
            $left->code,
        ] <=> [
            $right->name,
            $right->code,
        ]);

        return array_slice($results, 0, $limit);
    }

    private function matches(ReferenceEntryData $entry, string $query): bool
    {
        return str_contains(mb_strtolower($entry->code), $query)
            || str_contains(mb_strtolower($entry->name), $query);
    }

    private function normalizedQuery(?string $query): ?string
    {
        if (! is_string($query)) {
            return null;
        }

        $normalized = mb_strtolower(trim($query));

        return $normalized !== '' ? $normalized : null;
    }
}
