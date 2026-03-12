<?php

namespace App\Modules\Reporting\Application\Services;

use App\Modules\Billing\Application\Data\InvoiceSearchCriteria;
use App\Modules\Billing\Application\Services\InvoiceReadService;
use App\Modules\Insurance\Application\Data\ClaimSearchCriteria;
use App\Modules\Insurance\Application\Services\ClaimReadService;
use App\Modules\Patient\Application\Data\PatientSearchCriteria;
use App\Modules\Patient\Application\Services\PatientReadService;
use App\Modules\Provider\Application\Data\ProviderSearchCriteria;
use App\Modules\Provider\Application\Services\ProviderReadService;
use App\Modules\Reporting\Application\Data\GeneratedReportArtifactData;
use App\Modules\Reporting\Application\Data\ReportData;
use App\Modules\Scheduling\Application\Data\AppointmentSearchCriteria;
use App\Modules\Scheduling\Application\Services\AppointmentReadService;
use Carbon\CarbonImmutable;

final class ReportGenerationService
{
    public function __construct(
        private readonly PatientReadService $patientReadService,
        private readonly ProviderReadService $providerReadService,
        private readonly AppointmentReadService $appointmentReadService,
        private readonly InvoiceReadService $invoiceReadService,
        private readonly ClaimReadService $claimReadService,
        private readonly ReportCsvSerializer $reportCsvSerializer,
    ) {}

    public function generate(ReportData $report): GeneratedReportArtifactData
    {
        $generatedAt = CarbonImmutable::now();
        $rows = $this->rows($report);
        $contents = $this->reportCsvSerializer->serialize($rows);

        return new GeneratedReportArtifactData(
            fileName: sprintf('%s-report-%s.csv', $report->source, $generatedAt->format('Ymd-His')),
            contents: $contents,
            rowCount: count($rows),
            generatedAt: $generatedAt,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(ReportData $report): array
    {
        return match ($report->source) {
            'patients' => array_map(
                static fn ($patient): array => $patient->toArray(),
                $this->patientReadService->search(new PatientSearchCriteria(
                    query: $this->stringFilter($report, 'q'),
                    sex: $this->stringFilter($report, 'sex'),
                    cityCode: $this->stringFilter($report, 'city_code'),
                    districtCode: $this->stringFilter($report, 'district_code'),
                    birthDateFrom: $this->stringFilter($report, 'birth_date_from'),
                    birthDateTo: $this->stringFilter($report, 'birth_date_to'),
                    createdFrom: $this->stringFilter($report, 'created_from'),
                    createdTo: $this->stringFilter($report, 'created_to'),
                    hasEmail: $this->booleanFilter($report, 'has_email'),
                    hasPhone: $this->booleanFilter($report, 'has_phone'),
                    limit: $this->limit($report),
                )),
            ),
            'providers' => array_map(
                static fn ($provider): array => $provider->toArray(),
                $this->providerReadService->search(new ProviderSearchCriteria(
                    query: $this->stringFilter($report, 'q'),
                    providerType: $this->stringFilter($report, 'provider_type'),
                    clinicId: $this->stringFilter($report, 'clinic_id'),
                    hasEmail: $this->booleanFilter($report, 'has_email'),
                    hasPhone: $this->booleanFilter($report, 'has_phone'),
                    limit: $this->limit($report),
                )),
            ),
            'appointments' => array_map(
                static fn ($appointment): array => $appointment->toArray(),
                $this->appointmentReadService->search(new AppointmentSearchCriteria(
                    query: $this->stringFilter($report, 'q'),
                    status: $this->stringFilter($report, 'status'),
                    patientId: $this->stringFilter($report, 'patient_id'),
                    providerId: $this->stringFilter($report, 'provider_id'),
                    clinicId: $this->stringFilter($report, 'clinic_id'),
                    roomId: $this->stringFilter($report, 'room_id'),
                    scheduledFrom: $this->stringFilter($report, 'scheduled_from'),
                    scheduledTo: $this->stringFilter($report, 'scheduled_to'),
                    createdFrom: $this->stringFilter($report, 'created_from'),
                    createdTo: $this->stringFilter($report, 'created_to'),
                    limit: $this->limit($report),
                )),
            ),
            'invoices' => array_map(
                static fn ($invoice): array => $invoice->toArray(),
                $this->invoiceReadService->search(new InvoiceSearchCriteria(
                    query: $this->stringFilter($report, 'q'),
                    status: $this->stringFilter($report, 'status'),
                    patientId: $this->stringFilter($report, 'patient_id'),
                    issuedFrom: $this->stringFilter($report, 'issued_from'),
                    issuedTo: $this->stringFilter($report, 'issued_to'),
                    dueFrom: $this->stringFilter($report, 'due_from'),
                    dueTo: $this->stringFilter($report, 'due_to'),
                    createdFrom: $this->stringFilter($report, 'created_from'),
                    createdTo: $this->stringFilter($report, 'created_to'),
                    limit: $this->limit($report),
                )),
            ),
            'claims' => array_map(
                static fn ($claim): array => $claim->toArray(),
                $this->claimReadService->search(new ClaimSearchCriteria(
                    query: $this->stringFilter($report, 'q'),
                    status: $this->stringFilter($report, 'status'),
                    payerId: $this->stringFilter($report, 'payer_id'),
                    patientId: $this->stringFilter($report, 'patient_id'),
                    invoiceId: $this->stringFilter($report, 'invoice_id'),
                    serviceDateFrom: $this->stringFilter($report, 'service_date_from'),
                    serviceDateTo: $this->stringFilter($report, 'service_date_to'),
                    createdFrom: $this->stringFilter($report, 'created_from'),
                    createdTo: $this->stringFilter($report, 'created_to'),
                    limit: $this->limit($report),
                )),
            ),
            default => [],
        };
    }

    private function booleanFilter(ReportData $report, string $key): ?bool
    {
        $value = $this->filterValue($report, $key);

        return is_bool($value) ? $value : null;
    }

    private function limit(ReportData $report): int
    {
        $value = $this->filterValue($report, 'limit');

        return is_int($value) ? $value : 250;
    }

    private function stringFilter(ReportData $report, string $key): ?string
    {
        $value = $this->filterValue($report, $key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, mixed>|bool|float|int|string|null
     */
    private function filterValue(ReportData $report, string $key): array|bool|float|int|string|null
    {
        return $this->normalizeFilterEntry($report->filters[$key] ?? null);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<string, mixed>
     */
    private function normalizeArrayValue(array $value): array
    {
        /** @var array<string, mixed> $normalized */
        $normalized = [];

        array_walk($value, function (mixed $item, int|string $key) use (&$normalized): void {
            if (! is_string($key)) {
                return;
            }

            $normalized[$key] = $this->normalizeFilterEntry($item);
        });

        return $normalized;
    }

    /**
     * @return array<string, mixed>|bool|float|int|string|null
     */
    private function normalizeFilterEntry(mixed $value): array|bool|float|int|string|null
    {
        if (is_array($value)) {
            return $this->normalizeArrayValue($value);
        }

        return is_scalar($value) || $value === null ? $value : null;
    }
}
