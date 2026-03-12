<?php

namespace App\Shared\Infrastructure\Reference;

use App\Shared\Application\Contracts\ReferenceCatalogRepository;
use App\Shared\Application\Data\ReferenceEntryData;

final class ConfigReferenceCatalogRepository implements ReferenceCatalogRepository
{
    #[\Override]
    public function list(string $catalog): array
    {
        $entries = config('reference-data.'.$catalog, []);

        if (! is_array($entries)) {
            return [];
        }

        $results = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $code = $this->stringValue($entry['code'] ?? null);
            $name = $this->stringValue($entry['name'] ?? null);

            if ($code === '' || $name === '') {
                continue;
            }

            $results[] = new ReferenceEntryData(
                code: $code,
                name: $name,
                isActive: (bool) ($entry['is_active'] ?? true),
                metadata: $this->metadataValue($entry['metadata'] ?? null),
            );
        }

        return $results;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataValue(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        /** @var array<array-key, scalar|array<array-key, mixed>|null> $items */
        $items = $value;
        /** @var array<string, mixed> $metadata */
        $metadata = [];

        array_walk($items, function (mixed $item, int|string $key) use (&$metadata): void {
            if (is_string($key)) {
                $metadata[$key] = $this->normalizeMetadataItem($item);
            }
        });

        return $metadata;
    }

    /**
     * @return array<string, mixed>|bool|float|int|string|null
     */
    private function normalizeMetadataItem(mixed $item): array|bool|float|int|string|null
    {
        if (is_array($item)) {
            return $this->metadataValue($item);
        }

        return is_scalar($item) || $item === null ? $item : null;
    }
}
