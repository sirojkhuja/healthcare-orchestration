<?php

namespace App\Modules\Lab\Application\Services;

use App\Modules\Lab\Application\Contracts\LabProviderGatewayRegistry;
use App\Modules\Lab\Application\Data\LabTestData;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class LabTestAttributeNormalizer
{
    public function __construct(
        private readonly LabProviderGatewayRegistry $labProviderGatewayRegistry,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     code: string,
     *     name: string,
     *     description: ?string,
     *     specimen_type: string,
     *     result_type: string,
     *     unit: ?string,
     *     reference_range: ?string,
     *     lab_provider_key: string,
     *     external_test_code: ?string,
     *     is_active: bool
     * }
     */
    public function normalizeCreate(array $attributes): array
    {
        $normalized = [
            'code' => $this->requiredTrimmedString($attributes['code'] ?? null),
            'name' => $this->requiredTrimmedString($attributes['name'] ?? null),
            'description' => $this->nullableTrimmedString($attributes['description'] ?? null),
            'specimen_type' => $this->requiredTrimmedString($attributes['specimen_type'] ?? null),
            'result_type' => $this->requiredTrimmedString($attributes['result_type'] ?? null),
            'unit' => $this->nullableTrimmedString($attributes['unit'] ?? null),
            'reference_range' => $this->nullableTrimmedString($attributes['reference_range'] ?? null),
            'lab_provider_key' => $this->requiredTrimmedString($attributes['lab_provider_key'] ?? null),
            'external_test_code' => $this->nullableTrimmedString($attributes['external_test_code'] ?? null),
            'is_active' => (bool) ($attributes['is_active'] ?? true),
        ];

        $this->assertCandidate($normalized['lab_provider_key'], $normalized['result_type']);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function normalizePatch(LabTestData $labTest, array $attributes): array
    {
        $candidate = [
            'code' => array_key_exists('code', $attributes)
                ? $this->requiredTrimmedString($attributes['code'])
                : $labTest->code,
            'name' => array_key_exists('name', $attributes)
                ? $this->requiredTrimmedString($attributes['name'])
                : $labTest->name,
            'description' => array_key_exists('description', $attributes)
                ? $this->nullableTrimmedString($attributes['description'])
                : $labTest->description,
            'specimen_type' => array_key_exists('specimen_type', $attributes)
                ? $this->requiredTrimmedString($attributes['specimen_type'])
                : $labTest->specimenType,
            'result_type' => array_key_exists('result_type', $attributes)
                ? $this->requiredTrimmedString($attributes['result_type'])
                : $labTest->resultType,
            'unit' => array_key_exists('unit', $attributes)
                ? $this->nullableTrimmedString($attributes['unit'])
                : $labTest->unit,
            'reference_range' => array_key_exists('reference_range', $attributes)
                ? $this->nullableTrimmedString($attributes['reference_range'])
                : $labTest->referenceRange,
            'lab_provider_key' => array_key_exists('lab_provider_key', $attributes)
                ? $this->requiredTrimmedString($attributes['lab_provider_key'])
                : $labTest->labProviderKey,
            'external_test_code' => array_key_exists('external_test_code', $attributes)
                ? $this->nullableTrimmedString($attributes['external_test_code'])
                : $labTest->externalTestCode,
            'is_active' => array_key_exists('is_active', $attributes)
                ? (bool) $attributes['is_active']
                : $labTest->isActive,
        ];

        $this->assertCandidate($candidate['lab_provider_key'], $candidate['result_type']);

        $updates = [];

        foreach ($candidate as $key => $value) {
            $currentValue = match ($key) {
                'code' => $labTest->code,
                'name' => $labTest->name,
                'description' => $labTest->description,
                'specimen_type' => $labTest->specimenType,
                'result_type' => $labTest->resultType,
                'unit' => $labTest->unit,
                'reference_range' => $labTest->referenceRange,
                'lab_provider_key' => $labTest->labProviderKey,
                'external_test_code' => $labTest->externalTestCode,
                'is_active' => $labTest->isActive,
            };

            if ($value !== $currentValue) {
                $updates[$key] = $value;
            }
        }

        return $updates;
    }

    private function assertCandidate(string $labProviderKey, string $resultType): void
    {
        if (! preg_match('/^[a-z0-9._-]+$/', $labProviderKey)) {
            throw new UnprocessableEntityHttpException('The lab_provider_key field must use lowercase slug format.');
        }

        if (! in_array($resultType, ['numeric', 'text', 'boolean', 'json'], true)) {
            throw new UnprocessableEntityHttpException('The result_type field must contain a supported lab result type.');
        }

        $this->labProviderGatewayRegistry->resolve($labProviderKey);
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
