<?php

namespace App\Modules\Lab\Application\Services;

use App\Modules\Lab\Application\Contracts\LabProviderGatewayRegistry;
use App\Modules\Lab\Application\Contracts\LabTestRepository;
use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Application\Data\LabTestData;
use App\Modules\Patient\Application\Contracts\PatientRepository;
use App\Modules\Provider\Application\Contracts\ProviderRepository;
use App\Modules\Treatment\Application\Contracts\EncounterRepository;
use App\Modules\Treatment\Application\Contracts\TreatmentItemRepository;
use App\Modules\Treatment\Domain\TreatmentPlans\TreatmentItemType;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class LabOrderAttributeNormalizer
{
    public function __construct(
        private readonly PatientRepository $patientRepository,
        private readonly ProviderRepository $providerRepository,
        private readonly EncounterRepository $encounterRepository,
        private readonly TreatmentItemRepository $treatmentItemRepository,
        private readonly LabTestRepository $labTestRepository,
        private readonly LabProviderGatewayRegistry $labProviderGatewayRegistry,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     patient_id: string,
     *     provider_id: string,
     *     encounter_id: ?string,
     *     treatment_item_id: ?string,
     *     lab_test_id: ?string,
     *     lab_provider_key: string,
     *     requested_test_code: string,
     *     requested_test_name: string,
     *     requested_specimen_type: string,
     *     requested_result_type: string,
     *     status: string,
     *     ordered_at: CarbonImmutable,
     *     timezone: string,
     *     notes: ?string,
     *     external_order_id: null,
     *     sent_at: null,
     *     specimen_collected_at: null,
     *     specimen_received_at: null,
     *     completed_at: null,
     *     canceled_at: null,
     *     cancel_reason: null,
     *     last_transition: null
     * }
     */
    public function normalizeCreate(array $attributes, string $tenantId): array
    {
        $labTest = $this->labTestOrFail(
            $tenantId,
            $this->requiredTrimmedString($attributes['lab_test_id'] ?? null),
            'The lab_test_id field must reference an active lab test in the current tenant.',
        );

        $candidate = [
            'patient_id' => $this->requiredTrimmedString($attributes['patient_id'] ?? null),
            'provider_id' => $this->requiredTrimmedString($attributes['provider_id'] ?? null),
            'encounter_id' => $this->nullableTrimmedString($attributes['encounter_id'] ?? null),
            'treatment_item_id' => $this->nullableTrimmedString($attributes['treatment_item_id'] ?? null),
            'lab_test_id' => $labTest->testId,
            'lab_provider_key' => $this->requiredTrimmedString($attributes['lab_provider_key'] ?? null),
            'ordered_at' => CarbonImmutable::parse($this->requiredTrimmedString($attributes['ordered_at'] ?? null)),
            'timezone' => $this->requiredTrimmedString($attributes['timezone'] ?? null),
            'notes' => $this->nullableTrimmedString($attributes['notes'] ?? null),
        ];

        $this->assertCandidate($tenantId, $candidate, $labTest);

        return [
            ...$candidate,
            ...$this->requestedSnapshot($labTest),
            'status' => 'draft',
            'external_order_id' => null,
            'sent_at' => null,
            'specimen_collected_at' => null,
            'specimen_received_at' => null,
            'completed_at' => null,
            'canceled_at' => null,
            'cancel_reason' => null,
            'last_transition' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function normalizePatch(LabOrderData $order, array $attributes): array
    {
        $labTestId = array_key_exists('lab_test_id', $attributes)
            ? $this->nullableTrimmedString($attributes['lab_test_id'])
            : $order->labTestId;
        $labTest = $labTestId !== null
            ? $this->labTestOrFail(
                $order->tenantId,
                $labTestId,
                'The lab_test_id field must reference an active lab test in the current tenant.',
            )
            : null;

        $candidate = [
            'patient_id' => array_key_exists('patient_id', $attributes)
                ? $this->requiredTrimmedString($attributes['patient_id'])
                : $order->patientId,
            'provider_id' => array_key_exists('provider_id', $attributes)
                ? $this->requiredTrimmedString($attributes['provider_id'])
                : $order->providerId,
            'encounter_id' => array_key_exists('encounter_id', $attributes)
                ? $this->nullableTrimmedString($attributes['encounter_id'])
                : $order->encounterId,
            'treatment_item_id' => array_key_exists('treatment_item_id', $attributes)
                ? $this->nullableTrimmedString($attributes['treatment_item_id'])
                : $order->treatmentItemId,
            'lab_test_id' => $labTest?->testId,
            'lab_provider_key' => array_key_exists('lab_provider_key', $attributes)
                ? $this->requiredTrimmedString($attributes['lab_provider_key'])
                : $order->labProviderKey,
            'ordered_at' => array_key_exists('ordered_at', $attributes)
                ? CarbonImmutable::parse($this->requiredTrimmedString($attributes['ordered_at']))
                : $order->orderedAt,
            'timezone' => array_key_exists('timezone', $attributes)
                ? $this->requiredTrimmedString($attributes['timezone'])
                : $order->timezone,
            'notes' => array_key_exists('notes', $attributes)
                ? $this->nullableTrimmedString($attributes['notes'])
                : $order->notes,
        ];

        $this->assertCandidate($order->tenantId, $candidate, $labTest);
        $candidateWithSnapshot = $labTest instanceof LabTestData ? [
            ...$candidate,
            ...$this->requestedSnapshot($labTest),
        ] : $candidate;

        $updates = [];

        foreach ([
            'patient_id' => $order->patientId,
            'provider_id' => $order->providerId,
            'encounter_id' => $order->encounterId,
            'treatment_item_id' => $order->treatmentItemId,
            'lab_test_id' => $order->labTestId,
            'lab_provider_key' => $order->labProviderKey,
            'requested_test_code' => $order->requestedTestCode,
            'requested_test_name' => $order->requestedTestName,
            'requested_specimen_type' => $order->requestedSpecimenType,
            'requested_result_type' => $order->requestedResultType,
            'timezone' => $order->timezone,
            'notes' => $order->notes,
        ] as $key => $current) {
            if (array_key_exists($key, $candidateWithSnapshot) && $candidateWithSnapshot[$key] !== $current) {
                $updates[$key] = $candidateWithSnapshot[$key];
            }
        }

        if (! $candidate['ordered_at']->equalTo($order->orderedAt)) {
            $updates['ordered_at'] = $candidate['ordered_at'];
        }

        return $updates;
    }

    /**
     * @param  array{
     *     patient_id: string,
     *     provider_id: string,
     *     encounter_id: ?string,
     *     treatment_item_id: ?string,
     *     lab_test_id: ?string,
     *     lab_provider_key: string,
     *     ordered_at: CarbonImmutable,
     *     timezone: string,
     *     notes: ?string
     * }  $candidate
     */
    private function assertCandidate(string $tenantId, array $candidate, ?LabTestData $labTest): void
    {
        if ($this->patientRepository->findInTenant($tenantId, $candidate['patient_id']) === null) {
            throw new UnprocessableEntityHttpException('The patient_id field must reference an active patient in the current tenant.');
        }

        if ($this->providerRepository->findInTenant($tenantId, $candidate['provider_id']) === null) {
            throw new UnprocessableEntityHttpException('The provider_id field must reference an active provider in the current tenant.');
        }

        if (! preg_match('/^[a-z0-9._-]+$/', $candidate['lab_provider_key'])) {
            throw new UnprocessableEntityHttpException('The lab_provider_key field must use lowercase slug format.');
        }

        $this->labProviderGatewayRegistry->resolve($candidate['lab_provider_key']);

        if ($labTest instanceof LabTestData) {
            if (! $labTest->isActive) {
                throw new UnprocessableEntityHttpException('The lab_test_id field must reference an active lab test in the current tenant.');
            }

            if ($labTest->labProviderKey !== $candidate['lab_provider_key']) {
                throw new UnprocessableEntityHttpException('The lab_provider_key field must match the selected lab test provider.');
            }
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

                if ($item->itemType !== TreatmentItemType::LAB->value) {
                    throw new UnprocessableEntityHttpException('The treatment_item_id field must reference a lab treatment item.');
                }
            }

            return;
        }

        if ($candidate['treatment_item_id'] !== null) {
            throw new UnprocessableEntityHttpException('The treatment_item_id field requires encounter_id.');
        }
    }

    private function labTestOrFail(string $tenantId, ?string $labTestId, string $message): LabTestData
    {
        if ($labTestId === null) {
            throw new UnprocessableEntityHttpException($message);
        }

        $labTest = $this->labTestRepository->findInTenant($tenantId, $labTestId);

        if (! $labTest instanceof LabTestData || ! $labTest->isActive) {
            throw new UnprocessableEntityHttpException($message);
        }

        return $labTest;
    }

    /**
     * @return array{
     *     requested_test_code: string,
     *     requested_test_name: string,
     *     requested_specimen_type: string,
     *     requested_result_type: string
     * }
     */
    private function requestedSnapshot(LabTestData $labTest): array
    {
        return [
            'requested_test_code' => $labTest->code,
            'requested_test_name' => $labTest->name,
            'requested_specimen_type' => $labTest->specimenType,
            'requested_result_type' => $labTest->resultType,
        ];
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
