<?php

namespace App\Modules\Reporting\Application\Services;

use App\Modules\Patient\Domain\Patients\PatientSex;
use App\Modules\Provider\Domain\Providers\ProviderType;
use App\Modules\Scheduling\Domain\Appointments\AppointmentStatus;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ReportFilterNormalizer
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function normalize(string $source, array $filters): array
    {
        return match ($source) {
            'patients' => $this->patients($filters),
            'providers' => $this->providers($filters),
            'appointments' => $this->appointments($filters),
            'invoices' => $this->datesAndScalars($filters, ['status', 'patient_id'], ['issued_from', 'issued_to', 'due_from', 'due_to', 'created_from', 'created_to']),
            'claims' => $this->datesAndScalars($filters, ['status', 'payer_id', 'patient_id', 'invoice_id'], ['service_date_from', 'service_date_to', 'created_from', 'created_to']),
            default => throw new UnprocessableEntityHttpException('The report source is not supported.'),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function appointments(array $filters): array
    {
        $normalized = $this->datesAndScalars($filters, ['status', 'patient_id', 'provider_id', 'clinic_id', 'room_id'], ['scheduled_from', 'scheduled_to', 'created_from', 'created_to']);

        if (is_string($normalized['status'] ?? null) && ! in_array($normalized['status'], AppointmentStatus::all(), true)) {
            throw new UnprocessableEntityHttpException('Appointment report status filter is not supported.');
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $scalarKeys
     * @param  list<string>  $dateKeys
     * @return array<string, mixed>
     */
    private function datesAndScalars(array $filters, array $scalarKeys, array $dateKeys): array
    {
        $normalized = $this->base($filters);

        foreach ($scalarKeys as $key) {
            $value = $this->stringValue($filters[$key] ?? null);

            if ($value !== null) {
                $normalized[$key] = $value;
            }
        }

        foreach ($dateKeys as $key) {
            $value = $this->stringValue($filters[$key] ?? null);

            if ($value !== null) {
                $normalized[$key] = $this->dateValue($value, $key);
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function patients(array $filters): array
    {
        $normalized = $this->datesAndScalars($filters, ['city_code', 'district_code'], ['birth_date_from', 'birth_date_to', 'created_from', 'created_to']);
        $sex = $this->stringValue($filters['sex'] ?? null);

        if ($sex !== null && ! in_array($sex, PatientSex::all(), true)) {
            throw new UnprocessableEntityHttpException('Patient report sex filter is not supported.');
        }

        if ($sex !== null) {
            $normalized['sex'] = $sex;
        }

        foreach (['has_email', 'has_phone'] as $key) {
            $value = $this->booleanValue($filters[$key] ?? null);

            if ($value !== null) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function providers(array $filters): array
    {
        $normalized = $this->base($filters);
        $providerType = $this->stringValue($filters['provider_type'] ?? null);

        if ($providerType !== null && ! in_array($providerType, ProviderType::all(), true)) {
            throw new UnprocessableEntityHttpException('Provider report type filter is not supported.');
        }

        if ($providerType !== null) {
            $normalized['provider_type'] = $providerType;
        }

        foreach (['clinic_id'] as $key) {
            $value = $this->stringValue($filters[$key] ?? null);

            if ($value !== null) {
                $normalized[$key] = $value;
            }
        }

        foreach (['has_email', 'has_phone'] as $key) {
            $value = $this->booleanValue($filters[$key] ?? null);

            if ($value !== null) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function base(array $filters): array
    {
        /** @var array<string, mixed> $normalized */
        $normalized = [];
        $query = $this->stringValue($filters['q'] ?? null);

        if ($query !== null) {
            $normalized['q'] = $query;
        }

        $limit = $this->integerValue($filters['limit'] ?? null);
        $normalized['limit'] = $limit ?? 250;

        if ($normalized['limit'] < 1 || $normalized['limit'] > 1000) {
            throw new UnprocessableEntityHttpException('Report filter limit must be between 1 and 1000.');
        }

        return $normalized;
    }

    private function booleanValue(mixed $value): ?bool
    {
        return match ($value) {
            true, 'true', '1', 1 => true,
            false, 'false', '0', 0 => false,
            default => null,
        };
    }

    private function dateValue(string $value, string $key): string
    {
        $normalized = trim($value);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
            throw new UnprocessableEntityHttpException(sprintf('The %s filter must use YYYY-MM-DD format.', $key));
        }

        return $normalized;
    }

    private function integerValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
