<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Application\Data\PriceListData;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PriceListAttributeNormalizer
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
            'description' => $this->nullableString($attributes['description'] ?? null),
            'currency' => $this->normalizeCurrency($attributes['currency'] ?? null),
            'is_default' => array_key_exists('is_default', $attributes) ? (bool) $attributes['is_default'] : false,
            'is_active' => array_key_exists('is_active', $attributes) ? (bool) $attributes['is_active'] : true,
            'effective_from' => $this->dateString($attributes['effective_from'] ?? null),
            'effective_to' => $this->dateString($attributes['effective_to'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function normalizePatch(PriceListData $current, array $attributes): array
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

        if (array_key_exists('description', $attributes)) {
            $description = $this->nullableString($attributes['description']);

            if ($description !== $current->description) {
                $updates['description'] = $description;
            }
        }

        if (array_key_exists('currency', $attributes)) {
            $currency = $this->normalizeCurrency($attributes['currency']);

            if ($currency !== $current->currency) {
                $updates['currency'] = $currency;
            }
        }

        if (array_key_exists('is_default', $attributes)) {
            $isDefault = (bool) $attributes['is_default'];

            if ($isDefault !== $current->isDefault) {
                $updates['is_default'] = $isDefault;
            }
        }

        if (array_key_exists('is_active', $attributes)) {
            $isActive = (bool) $attributes['is_active'];

            if ($isActive !== $current->isActive) {
                $updates['is_active'] = $isActive;
            }
        }

        if (array_key_exists('effective_from', $attributes)) {
            $effectiveFrom = $this->dateString($attributes['effective_from']);
            $currentEffectiveFrom = $current->effectiveFrom?->toDateString();

            if ($effectiveFrom !== $currentEffectiveFrom) {
                $updates['effective_from'] = $effectiveFrom;
            }
        }

        if (array_key_exists('effective_to', $attributes)) {
            $effectiveTo = $this->dateString($attributes['effective_to']);
            $currentEffectiveTo = $current->effectiveTo?->toDateString();

            if ($effectiveTo !== $currentEffectiveTo) {
                $updates['effective_to'] = $effectiveTo;
            }
        }

        return $updates;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{service_id: string, amount: string}>
     */
    public function normalizeItems(array $items): array
    {
        return array_map(function (array $item): array {
            return [
                'service_id' => $this->requiredString($item['service_id'] ?? null),
                'amount' => $this->normalizeAmount($item['amount'] ?? null),
            ];
        }, $items);
    }

    private function dateString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('Y-m-d', $normalized);

        if (! $date instanceof CarbonImmutable) {
            throw new UnprocessableEntityHttpException('Dates must use the YYYY-MM-DD format.');
        }

        return $date->toDateString();
    }

    private function normalizeAmount(mixed $value): string
    {
        $amount = match (true) {
            is_int($value) => sprintf('%d.00', $value),
            is_float($value) => number_format($value, 2, '.', ''),
            is_string($value) => $this->normalizeAmountString($value),
            default => '',
        };

        if ($amount === '' || (float) $amount <= 0.0) {
            throw new UnprocessableEntityHttpException('Each price list item amount must be greater than zero.');
        }

        return $amount;
    }

    private function normalizeAmountString(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '' || ! preg_match('/^\d{1,10}(\.\d{1,2})?$/', $normalized)) {
            return '';
        }

        if (! str_contains($normalized, '.')) {
            return $normalized.'.00';
        }

        $parts = explode('.', $normalized, 2);
        $whole = $parts[0];
        $decimal = $parts[1] ?? '';

        return $whole.'.'.str_pad($decimal, 2, '0');
    }

    private function normalizeCode(mixed $value): string
    {
        return strtoupper($this->requiredString($value));
    }

    private function normalizeCurrency(mixed $value): string
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
