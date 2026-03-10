<?php

namespace App\Modules\Pharmacy\Application\Services;

use App\Modules\Pharmacy\Application\Data\MedicationData;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class MedicationAttributeNormalizer
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     code: string,
     *     name: string,
     *     generic_name: ?string,
     *     form: ?string,
     *     strength: ?string,
     *     description: ?string,
     *     is_active: bool
     * }
     */
    public function normalizeCreate(array $attributes): array
    {
        return [
            'code' => $this->normalizedCode($attributes['code'] ?? null),
            'name' => $this->requiredString($attributes['name'] ?? null, 'name'),
            'generic_name' => $this->nullableString($attributes['generic_name'] ?? null),
            'form' => $this->nullableString($attributes['form'] ?? null),
            'strength' => $this->nullableString($attributes['strength'] ?? null),
            'description' => $this->nullableString($attributes['description'] ?? null),
            'is_active' => (bool) ($attributes['is_active'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function normalizePatch(MedicationData $medication, array $attributes): array
    {
        $candidate = [
            'code' => array_key_exists('code', $attributes)
                ? $this->normalizedCode($attributes['code'])
                : $medication->code,
            'name' => array_key_exists('name', $attributes)
                ? $this->requiredString($attributes['name'], 'name')
                : $medication->name,
            'generic_name' => array_key_exists('generic_name', $attributes)
                ? $this->nullableString($attributes['generic_name'])
                : $medication->genericName,
            'form' => array_key_exists('form', $attributes)
                ? $this->nullableString($attributes['form'])
                : $medication->form,
            'strength' => array_key_exists('strength', $attributes)
                ? $this->nullableString($attributes['strength'])
                : $medication->strength,
            'description' => array_key_exists('description', $attributes)
                ? $this->nullableString($attributes['description'])
                : $medication->description,
            'is_active' => array_key_exists('is_active', $attributes)
                ? (bool) $attributes['is_active']
                : $medication->isActive,
        ];

        $updates = [];

        foreach ([
            'code' => $medication->code,
            'name' => $medication->name,
            'generic_name' => $medication->genericName,
            'form' => $medication->form,
            'strength' => $medication->strength,
            'description' => $medication->description,
            'is_active' => $medication->isActive,
        ] as $key => $current) {
            if ($candidate[$key] !== $current) {
                $updates[$key] = $candidate[$key];
            }
        }

        return $updates;
    }

    private function normalizedCode(mixed $value): string
    {
        $string = $this->requiredString($value, 'code');

        return mb_strtoupper($string);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function requiredString(mixed $value, string $field): string
    {
        $normalized = $this->nullableString($value);

        if ($normalized === null) {
            throw new UnprocessableEntityHttpException('The '.$field.' field is required.');
        }

        return $normalized;
    }
}
