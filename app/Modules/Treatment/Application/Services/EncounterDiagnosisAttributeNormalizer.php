<?php

namespace App\Modules\Treatment\Application\Services;

use App\Modules\Treatment\Domain\Encounters\DiagnosisType;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class EncounterDiagnosisAttributeNormalizer
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     code: ?string,
     *     display_name: string,
     *     diagnosis_type: string,
     *     notes: ?string
     * }
     */
    public function normalizeCreate(array $attributes): array
    {
        $normalized = [
            'code' => $this->nullableTrimmedString($attributes['code'] ?? null),
            'display_name' => $this->requiredTrimmedString($attributes['display_name'] ?? null),
            'diagnosis_type' => $this->requiredTrimmedString($attributes['diagnosis_type'] ?? null),
            'notes' => $this->nullableTrimmedString($attributes['notes'] ?? null),
        ];

        if (! in_array($normalized['diagnosis_type'], DiagnosisType::all(), true)) {
            throw new UnprocessableEntityHttpException('The diagnosis_type field must contain a supported diagnosis type.');
        }

        return $normalized;
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function requiredTrimmedString(mixed $value): string
    {
        return $this->nullableTrimmedString($value) ?? '';
    }
}
