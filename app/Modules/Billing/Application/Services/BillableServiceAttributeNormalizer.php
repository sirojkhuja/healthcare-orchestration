<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Application\Data\BillableServiceData;

final class BillableServiceAttributeNormalizer
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function normalizeCreate(array $attributes): array
    {
        return [
            'code' => $this->normalizeCode($attributes['code'] ?? null),
            'name' => $this->requiredString($attributes['name'] ?? null),
            'category' => $this->nullableString($attributes['category'] ?? null),
            'unit' => $this->nullableString($attributes['unit'] ?? null),
            'description' => $this->nullableString($attributes['description'] ?? null),
            'is_active' => array_key_exists('is_active', $attributes) ? (bool) $attributes['is_active'] : true,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function normalizePatch(BillableServiceData $current, array $attributes): array
    {
        $updates = [];

        if (array_key_exists('code', $attributes)) {
            $code = $this->normalizeCode($attributes['code']);

            if ($code !== $current->code) {
                $updates['code'] = $code;
            }
        }

        if (array_key_exists('name', $attributes)) {
            $name = $this->requiredString($attributes['name']);

            if ($name !== $current->name) {
                $updates['name'] = $name;
            }
        }

        if (array_key_exists('category', $attributes)) {
            $category = $this->nullableString($attributes['category']);

            if ($category !== $current->category) {
                $updates['category'] = $category;
            }
        }

        if (array_key_exists('unit', $attributes)) {
            $unit = $this->nullableString($attributes['unit']);

            if ($unit !== $current->unit) {
                $updates['unit'] = $unit;
            }
        }

        if (array_key_exists('description', $attributes)) {
            $description = $this->nullableString($attributes['description']);

            if ($description !== $current->description) {
                $updates['description'] = $description;
            }
        }

        if (array_key_exists('is_active', $attributes)) {
            $isActive = (bool) $attributes['is_active'];

            if ($isActive !== $current->isActive) {
                $updates['is_active'] = $isActive;
            }
        }

        return $updates;
    }

    private function normalizeCode(mixed $value): string
    {
        return strtoupper($this->requiredString($value));
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function requiredString(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim($value);
    }
}
