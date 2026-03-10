<?php

namespace App\Modules\Treatment\Application\Services;

use App\Modules\Patient\Application\Contracts\PatientRepository;
use App\Modules\Provider\Application\Contracts\ProviderRepository;
use App\Modules\Treatment\Application\Data\TreatmentPlanData;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class TreatmentPlanAttributeNormalizer
{
    public function __construct(
        private readonly PatientRepository $patientRepository,
        private readonly ProviderRepository $providerRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     patient_id: string,
     *     provider_id: string,
     *     title: string,
     *     summary: ?string,
     *     goals: ?string,
     *     planned_start_date: ?string,
     *     planned_end_date: ?string
     * }
     */
    public function normalizeCreate(array $attributes, string $tenantId): array
    {
        $normalized = [
            'patient_id' => $this->requiredTrimmedString($attributes['patient_id'] ?? null, 'The patient_id field is required.'),
            'provider_id' => $this->requiredTrimmedString($attributes['provider_id'] ?? null, 'The provider_id field is required.'),
            'title' => $this->requiredTrimmedString($attributes['title'] ?? null, 'The title field is required.'),
            'summary' => $this->nullableTrimmedString($attributes['summary'] ?? null),
            'goals' => $this->nullableTrimmedString($attributes['goals'] ?? null),
            'planned_start_date' => $this->nullableDateString($attributes['planned_start_date'] ?? null),
            'planned_end_date' => $this->nullableDateString($attributes['planned_end_date'] ?? null),
        ];

        $this->assertCandidate($tenantId, $normalized);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function normalizePatch(TreatmentPlanData $plan, array $attributes): array
    {
        $candidate = [
            'patient_id' => array_key_exists('patient_id', $attributes)
                ? $this->requiredTrimmedString($attributes['patient_id'], 'The patient_id field is required.')
                : $plan->patientId,
            'provider_id' => array_key_exists('provider_id', $attributes)
                ? $this->requiredTrimmedString($attributes['provider_id'], 'The provider_id field is required.')
                : $plan->providerId,
            'title' => array_key_exists('title', $attributes)
                ? $this->requiredTrimmedString($attributes['title'], 'The title field is required.')
                : $plan->title,
            'summary' => array_key_exists('summary', $attributes)
                ? $this->nullableTrimmedString($attributes['summary'])
                : $plan->summary,
            'goals' => array_key_exists('goals', $attributes)
                ? $this->nullableTrimmedString($attributes['goals'])
                : $plan->goals,
            'planned_start_date' => array_key_exists('planned_start_date', $attributes)
                ? $this->nullableDateString($attributes['planned_start_date'])
                : $plan->plannedStartDate,
            'planned_end_date' => array_key_exists('planned_end_date', $attributes)
                ? $this->nullableDateString($attributes['planned_end_date'])
                : $plan->plannedEndDate,
        ];

        $this->assertCandidate($plan->tenantId, $candidate);

        $updates = [];

        foreach (['patient_id', 'provider_id', 'title', 'summary', 'goals', 'planned_start_date', 'planned_end_date'] as $field) {
            $current = match ($field) {
                'patient_id' => $plan->patientId,
                'provider_id' => $plan->providerId,
                'title' => $plan->title,
                'summary' => $plan->summary,
                'goals' => $plan->goals,
                'planned_start_date' => $plan->plannedStartDate,
                'planned_end_date' => $plan->plannedEndDate,
            };

            if ($candidate[$field] !== $current) {
                $updates[$field] = $candidate[$field];
            }
        }

        return $updates;
    }

    /**
     * @param  array{
     *     patient_id: string,
     *     provider_id: string,
     *     title: string,
     *     summary: ?string,
     *     goals: ?string,
     *     planned_start_date: ?string,
     *     planned_end_date: ?string
     * }  $candidate
     */
    private function assertCandidate(string $tenantId, array $candidate): void
    {
        if (! $this->patientRepository->findInTenant($tenantId, $candidate['patient_id'])) {
            throw new UnprocessableEntityHttpException('The patient_id field must reference an active patient in the current tenant.');
        }

        if (! $this->providerRepository->findInTenant($tenantId, $candidate['provider_id'])) {
            throw new UnprocessableEntityHttpException('The provider_id field must reference an active provider in the current tenant.');
        }

        if (
            $candidate['planned_start_date'] !== null
            && $candidate['planned_end_date'] !== null
            && $candidate['planned_end_date'] < $candidate['planned_start_date']
        ) {
            throw new UnprocessableEntityHttpException('The planned_end_date field must be on or after planned_start_date.');
        }
    }

    private function nullableDateString(mixed $value): ?string
    {
        $normalized = $this->nullableTrimmedString($value);

        if ($normalized === null) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('Y-m-d', $normalized, 'UTC');

        if (! $date instanceof CarbonImmutable || $date->toDateString() !== $normalized) {
            throw new UnprocessableEntityHttpException('Date values must use Y-m-d format.');
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

    private function requiredTrimmedString(mixed $value, string $message): string
    {
        return $this->nullableTrimmedString($value) ?? throw new UnprocessableEntityHttpException($message);
    }
}
