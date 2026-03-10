<?php

namespace App\Modules\Pharmacy\Application\Services;

use App\Modules\Patient\Application\Contracts\PatientRepository;
use App\Modules\Pharmacy\Application\Data\PrescriptionData;
use App\Modules\Provider\Application\Contracts\ProviderRepository;
use App\Modules\Treatment\Application\Contracts\EncounterRepository;
use App\Modules\Treatment\Application\Contracts\TreatmentItemRepository;
use App\Modules\Treatment\Domain\TreatmentPlans\TreatmentItemType;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PrescriptionAttributeNormalizer
{
    public function __construct(
        private readonly PatientRepository $patientRepository,
        private readonly ProviderRepository $providerRepository,
        private readonly EncounterRepository $encounterRepository,
        private readonly TreatmentItemRepository $treatmentItemRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     patient_id: string,
     *     provider_id: string,
     *     encounter_id: ?string,
     *     treatment_item_id: ?string,
     *     medication_name: string,
     *     medication_code: ?string,
     *     dosage: string,
     *     route: string,
     *     frequency: string,
     *     quantity: string,
     *     quantity_unit: ?string,
     *     authorized_refills: int,
     *     instructions: ?string,
     *     notes: ?string,
     *     starts_on: ?string,
     *     ends_on: ?string,
     *     status: string,
     *     issued_at: null,
     *     dispensed_at: null,
     *     canceled_at: null,
     *     cancel_reason: null,
     *     last_transition: null
     * }
     */
    public function normalizeCreate(array $attributes, string $tenantId): array
    {
        $candidate = [
            'patient_id' => $this->requiredTrimmedString($attributes['patient_id'] ?? null, 'patient_id'),
            'provider_id' => $this->requiredTrimmedString($attributes['provider_id'] ?? null, 'provider_id'),
            'encounter_id' => $this->nullableTrimmedString($attributes['encounter_id'] ?? null),
            'treatment_item_id' => $this->nullableTrimmedString($attributes['treatment_item_id'] ?? null),
            'medication_name' => $this->requiredTrimmedString($attributes['medication_name'] ?? null, 'medication_name'),
            'medication_code' => $this->nullableTrimmedString($attributes['medication_code'] ?? null),
            'dosage' => $this->requiredTrimmedString($attributes['dosage'] ?? null, 'dosage'),
            'route' => $this->requiredTrimmedString($attributes['route'] ?? null, 'route'),
            'frequency' => $this->requiredTrimmedString($attributes['frequency'] ?? null, 'frequency'),
            'quantity' => $this->numericString($attributes['quantity'] ?? null, 'quantity'),
            'quantity_unit' => $this->nullableTrimmedString($attributes['quantity_unit'] ?? null),
            'authorized_refills' => $this->authorizedRefills($attributes['authorized_refills'] ?? null),
            'instructions' => $this->nullableTrimmedString($attributes['instructions'] ?? null),
            'notes' => $this->nullableTrimmedString($attributes['notes'] ?? null),
            'starts_on' => $this->nullableDateString($attributes['starts_on'] ?? null),
            'ends_on' => $this->nullableDateString($attributes['ends_on'] ?? null),
        ];

        $this->assertCandidate($tenantId, $candidate);

        return [
            ...$candidate,
            'status' => 'draft',
            'issued_at' => null,
            'dispensed_at' => null,
            'canceled_at' => null,
            'cancel_reason' => null,
            'last_transition' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function normalizePatch(PrescriptionData $prescription, array $attributes): array
    {
        $candidate = [
            'patient_id' => array_key_exists('patient_id', $attributes)
                ? $this->requiredTrimmedString($attributes['patient_id'], 'patient_id')
                : $prescription->patientId,
            'provider_id' => array_key_exists('provider_id', $attributes)
                ? $this->requiredTrimmedString($attributes['provider_id'], 'provider_id')
                : $prescription->providerId,
            'encounter_id' => array_key_exists('encounter_id', $attributes)
                ? $this->nullableTrimmedString($attributes['encounter_id'])
                : $prescription->encounterId,
            'treatment_item_id' => array_key_exists('treatment_item_id', $attributes)
                ? $this->nullableTrimmedString($attributes['treatment_item_id'])
                : $prescription->treatmentItemId,
            'medication_name' => array_key_exists('medication_name', $attributes)
                ? $this->requiredTrimmedString($attributes['medication_name'], 'medication_name')
                : $prescription->medicationName,
            'medication_code' => array_key_exists('medication_code', $attributes)
                ? $this->nullableTrimmedString($attributes['medication_code'])
                : $prescription->medicationCode,
            'dosage' => array_key_exists('dosage', $attributes)
                ? $this->requiredTrimmedString($attributes['dosage'], 'dosage')
                : $prescription->dosage,
            'route' => array_key_exists('route', $attributes)
                ? $this->requiredTrimmedString($attributes['route'], 'route')
                : $prescription->route,
            'frequency' => array_key_exists('frequency', $attributes)
                ? $this->requiredTrimmedString($attributes['frequency'], 'frequency')
                : $prescription->frequency,
            'quantity' => array_key_exists('quantity', $attributes)
                ? $this->numericString($attributes['quantity'], 'quantity')
                : $prescription->quantity,
            'quantity_unit' => array_key_exists('quantity_unit', $attributes)
                ? $this->nullableTrimmedString($attributes['quantity_unit'])
                : $prescription->quantityUnit,
            'authorized_refills' => array_key_exists('authorized_refills', $attributes)
                ? $this->authorizedRefills($attributes['authorized_refills'])
                : $prescription->authorizedRefills,
            'instructions' => array_key_exists('instructions', $attributes)
                ? $this->nullableTrimmedString($attributes['instructions'])
                : $prescription->instructions,
            'notes' => array_key_exists('notes', $attributes)
                ? $this->nullableTrimmedString($attributes['notes'])
                : $prescription->notes,
            'starts_on' => array_key_exists('starts_on', $attributes)
                ? $this->nullableDateString($attributes['starts_on'])
                : $prescription->startsOn,
            'ends_on' => array_key_exists('ends_on', $attributes)
                ? $this->nullableDateString($attributes['ends_on'])
                : $prescription->endsOn,
        ];

        $this->assertCandidate($prescription->tenantId, $candidate);

        $updates = [];

        foreach ([
            'patient_id' => $prescription->patientId,
            'provider_id' => $prescription->providerId,
            'encounter_id' => $prescription->encounterId,
            'treatment_item_id' => $prescription->treatmentItemId,
            'medication_name' => $prescription->medicationName,
            'medication_code' => $prescription->medicationCode,
            'dosage' => $prescription->dosage,
            'route' => $prescription->route,
            'frequency' => $prescription->frequency,
            'quantity' => $prescription->quantity,
            'quantity_unit' => $prescription->quantityUnit,
            'authorized_refills' => $prescription->authorizedRefills,
            'instructions' => $prescription->instructions,
            'notes' => $prescription->notes,
            'starts_on' => $prescription->startsOn,
            'ends_on' => $prescription->endsOn,
        ] as $key => $current) {
            if ($candidate[$key] !== $current) {
                $updates[$key] = $candidate[$key];
            }
        }

        return $updates;
    }

    /**
     * @param  array{
     *     patient_id: string,
     *     provider_id: string,
     *     encounter_id: ?string,
     *     treatment_item_id: ?string,
     *     medication_name: string,
     *     medication_code: ?string,
     *     dosage: string,
     *     route: string,
     *     frequency: string,
     *     quantity: string,
     *     quantity_unit: ?string,
     *     authorized_refills: int,
     *     instructions: ?string,
     *     notes: ?string,
     *     starts_on: ?string,
     *     ends_on: ?string
     * }  $candidate
     */
    private function assertCandidate(string $tenantId, array $candidate): void
    {
        if ($this->patientRepository->findInTenant($tenantId, $candidate['patient_id']) === null) {
            throw new UnprocessableEntityHttpException('The patient_id field must reference an active patient in the current tenant.');
        }

        if ($this->providerRepository->findInTenant($tenantId, $candidate['provider_id']) === null) {
            throw new UnprocessableEntityHttpException('The provider_id field must reference an active provider in the current tenant.');
        }

        if ($candidate['starts_on'] !== null && $candidate['ends_on'] !== null && $candidate['starts_on'] > $candidate['ends_on']) {
            throw new UnprocessableEntityHttpException('The ends_on field must be on or after starts_on.');
        }

        if ($candidate['encounter_id'] !== null) {
            $encounter = $this->encounterRepository->findInTenant($tenantId, $candidate['encounter_id']);

            if ($encounter === null) {
                throw new UnprocessableEntityHttpException('The encounter_id field must reference an active encounter in the current tenant.');
            }

            if ($encounter->patientId !== $candidate['patient_id']) {
                throw new UnprocessableEntityHttpException('The patient_id field must match the linked encounter patient.');
            }

            if ($encounter->providerId !== $candidate['provider_id']) {
                throw new UnprocessableEntityHttpException('The provider_id field must match the linked encounter provider.');
            }

            if ($candidate['treatment_item_id'] !== null) {
                if ($encounter->treatmentPlanId === null) {
                    throw new UnprocessableEntityHttpException('The treatment_item_id field requires an encounter linked to a treatment plan.');
                }

                $item = $this->treatmentItemRepository->findInPlan(
                    $tenantId,
                    $encounter->treatmentPlanId,
                    $candidate['treatment_item_id'],
                );

                if ($item === null) {
                    throw new UnprocessableEntityHttpException('The treatment_item_id field must reference an item in the linked encounter treatment plan.');
                }

                if ($item->itemType !== TreatmentItemType::MEDICATION->value) {
                    throw new UnprocessableEntityHttpException('The treatment_item_id field must reference a medication treatment item.');
                }
            }

            return;
        }

        if ($candidate['treatment_item_id'] !== null) {
            throw new UnprocessableEntityHttpException('The treatment_item_id field requires encounter_id.');
        }
    }

    private function authorizedRefills(mixed $value): int
    {
        if (! is_numeric($value)) {
            throw new UnprocessableEntityHttpException('The authorized_refills field is required and must be an integer.');
        }

        $authorizedRefills = (int) $value;

        if ($authorizedRefills < 0 || $authorizedRefills > 99) {
            throw new UnprocessableEntityHttpException('The authorized_refills field must be between 0 and 99.');
        }

        return $authorizedRefills;
    }

    private function nullableDateString(mixed $value): ?string
    {
        $normalized = $this->nullableTrimmedString($value);

        return $normalized === '' ? null : $normalized;
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private function numericString(mixed $value, string $field): string
    {
        if (! is_numeric($value)) {
            throw new UnprocessableEntityHttpException(sprintf('The %s field must be numeric.', $field));
        }

        $normalized = trim((string) $value);

        if ((float) $normalized <= 0) {
            throw new UnprocessableEntityHttpException(sprintf('The %s field must be greater than zero.', $field));
        }

        return $normalized;
    }

    private function requiredTrimmedString(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new UnprocessableEntityHttpException(sprintf('The %s field is required.', $field));
        }

        return trim($value);
    }
}
