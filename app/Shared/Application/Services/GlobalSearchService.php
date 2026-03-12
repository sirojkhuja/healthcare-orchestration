<?php

namespace App\Shared\Application\Services;

use App\Modules\Billing\Application\Data\InvoiceSearchCriteria;
use App\Modules\Billing\Application\Services\InvoiceReadService;
use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\IdentityAccess\Application\Contracts\PermissionAuthorizer;
use App\Modules\Insurance\Application\Data\ClaimSearchCriteria;
use App\Modules\Insurance\Application\Services\ClaimReadService;
use App\Modules\Patient\Application\Data\PatientSearchCriteria;
use App\Modules\Patient\Application\Services\PatientReadService;
use App\Modules\Provider\Application\Data\ProviderData;
use App\Modules\Provider\Application\Data\ProviderSearchCriteria;
use App\Modules\Provider\Application\Services\ProviderReadService;
use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Application\Data\AppointmentSearchCriteria;
use App\Modules\Scheduling\Application\Services\AppointmentReadService;
use App\Shared\Application\Contracts\TenantContext;
use App\Shared\Application\Data\GlobalSearchCriteria;
use App\Shared\Application\Data\GlobalSearchItemData;
use App\Shared\Application\Data\GlobalSearchResultSetData;

final class GlobalSearchService
{
    private const PERMISSION_MAP = [
        'patient' => 'patients.view',
        'provider' => 'providers.view',
        'appointment' => 'appointments.view',
        'invoice' => 'billing.view',
        'claim' => 'claims.view',
    ];

    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
        private readonly PermissionAuthorizer $permissionAuthorizer,
        private readonly PatientReadService $patientReadService,
        private readonly ProviderReadService $providerReadService,
        private readonly AppointmentReadService $appointmentReadService,
        private readonly InvoiceReadService $invoiceReadService,
        private readonly ClaimReadService $claimReadService,
        private readonly TenantContext $tenantContext,
    ) {}

    public function search(GlobalSearchCriteria $criteria): GlobalSearchResultSetData
    {
        $requestedTypes = $criteria->normalizedTypes();
        $candidateTypes = $requestedTypes !== [] ? $requestedTypes : array_keys(self::PERMISSION_MAP);
        $accessibleTypes = array_values(array_filter(
            $candidateTypes,
            fn (string $type): bool => $this->canSearchType($type),
        ));
        $results = [];

        foreach ($accessibleTypes as $type) {
            $results[$type] = $this->searchType($type, $criteria);
        }

        return new GlobalSearchResultSetData(
            criteria: $criteria,
            results: $results,
            requestedTypes: $requestedTypes,
            returnedTypes: array_keys($results),
        );
    }

    private function canSearchType(string $type): bool
    {
        $permission = self::PERMISSION_MAP[$type] ?? null;

        if ($permission === null) {
            return false;
        }

        $request = $this->authenticatedRequestContext->current();
        $tenantId = $this->tenantContext->tenantId();

        return $tenantId !== null
            && $this->permissionAuthorizer->allows($request->user->id, $tenantId, $permission);
    }

    /**
     * @return list<GlobalSearchItemData>
     */
    private function searchType(string $type, GlobalSearchCriteria $criteria): array
    {
        return match ($type) {
            'patient' => $this->searchPatients($criteria),
            'provider' => $this->searchProviders($criteria),
            'appointment' => $this->searchAppointments($criteria),
            'invoice' => $this->searchInvoices($criteria),
            'claim' => $this->searchClaims($criteria),
            default => [],
        };
    }

    /**
     * @return list<GlobalSearchItemData>
     */
    private function searchPatients(GlobalSearchCriteria $criteria): array
    {
        return array_map(
            fn ($patient): GlobalSearchItemData => new GlobalSearchItemData(
                type: 'patient',
                id: $patient->patientId,
                title: trim($patient->firstName.' '.$patient->lastName),
                subtitle: $patient->email ?? $patient->phone ?? $patient->nationalId,
                status: $patient->deletedAt === null ? 'active' : 'deleted',
                score: $this->score([$patient->firstName, $patient->lastName, $patient->email, $patient->nationalId], $criteria),
                metadata: [
                    'birth_date' => $patient->birthDate->toDateString(),
                    'city_code' => $patient->cityCode,
                ],
            ),
            $this->patientReadService->search(new PatientSearchCriteria(
                query: $criteria->query,
                limit: $criteria->limitPerType,
            )),
        );
    }

    /**
     * @return list<GlobalSearchItemData>
     */
    private function searchProviders(GlobalSearchCriteria $criteria): array
    {
        return array_map(
            fn (ProviderData $provider): GlobalSearchItemData => new GlobalSearchItemData(
                type: 'provider',
                id: $provider->providerId,
                title: trim($provider->firstName.' '.$provider->lastName),
                subtitle: $provider->providerType,
                status: $provider->deletedAt === null ? 'active' : 'deleted',
                score: $this->score([$provider->firstName, $provider->lastName, $provider->email, $provider->phone], $criteria),
                metadata: [
                    'clinic_id' => $provider->clinicId,
                    'provider_type' => $provider->providerType,
                ],
            ),
            $this->providerReadService->search(new ProviderSearchCriteria(
                query: $criteria->query,
                limit: $criteria->limitPerType,
            )),
        );
    }

    /**
     * @return list<GlobalSearchItemData>
     */
    private function searchAppointments(GlobalSearchCriteria $criteria): array
    {
        return array_map(
            fn (AppointmentData $appointment): GlobalSearchItemData => new GlobalSearchItemData(
                type: 'appointment',
                id: $appointment->appointmentId,
                title: $appointment->patientDisplayName,
                subtitle: $appointment->providerDisplayName,
                status: $appointment->status,
                score: $this->score([
                    $appointment->appointmentId,
                    $appointment->patientDisplayName,
                    $appointment->providerDisplayName,
                ], $criteria),
                metadata: [
                    'scheduled_start_at' => $appointment->scheduledStartAt->toIso8601String(),
                    'clinic_name' => $appointment->clinicName,
                ],
            ),
            $this->appointmentReadService->search(new AppointmentSearchCriteria(
                query: $criteria->query,
                limit: $criteria->limitPerType,
            )),
        );
    }

    /**
     * @return list<GlobalSearchItemData>
     */
    private function searchInvoices(GlobalSearchCriteria $criteria): array
    {
        return array_map(
            fn ($invoice): GlobalSearchItemData => new GlobalSearchItemData(
                type: 'invoice',
                id: $invoice->invoiceId,
                title: $invoice->invoiceNumber,
                subtitle: $invoice->patientDisplayName,
                status: $invoice->status,
                score: $this->score([$invoice->invoiceNumber, $invoice->patientDisplayName], $criteria),
                metadata: [
                    'total_amount' => $invoice->totalAmount,
                    'currency' => $invoice->currency,
                ],
            ),
            $this->invoiceReadService->search(new InvoiceSearchCriteria(
                query: $criteria->query,
                limit: $criteria->limitPerType,
            )),
        );
    }

    /**
     * @return list<GlobalSearchItemData>
     */
    private function searchClaims(GlobalSearchCriteria $criteria): array
    {
        return array_map(
            fn ($claim): GlobalSearchItemData => new GlobalSearchItemData(
                type: 'claim',
                id: $claim->claimId,
                title: $claim->claimNumber,
                subtitle: $claim->payerName,
                status: $claim->status,
                score: $this->score([$claim->claimNumber, $claim->payerName, $claim->patientDisplayName], $criteria),
                metadata: [
                    'patient_name' => $claim->patientDisplayName,
                    'billed_amount' => $claim->billedAmount,
                ],
            ),
            $this->claimReadService->search(new ClaimSearchCriteria(
                query: $criteria->query,
                limit: $criteria->limitPerType,
            )),
        );
    }

    /**
     * @param  list<string|null>  $fields
     */
    private function score(array $fields, GlobalSearchCriteria $criteria): int
    {
        $query = $criteria->normalizedQuery();
        $score = 0;

        foreach ($fields as $field) {
            if (! is_string($field) || trim($field) === '') {
                continue;
            }

            $normalizedField = mb_strtolower(trim($field));

            if ($normalizedField === $query) {
                $score += 100;

                continue;
            }

            if (str_starts_with($normalizedField, $query)) {
                $score += 60;

                continue;
            }

            if (str_contains($normalizedField, $query)) {
                $score += 25;
            }
        }

        return $score;
    }
}
