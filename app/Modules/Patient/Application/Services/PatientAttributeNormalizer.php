<?php

namespace App\Modules\Patient\Application\Services;

use App\Modules\Patient\Application\Contracts\PatientRepository;
use App\Modules\Patient\Application\Data\PatientData;
use App\Modules\Patient\Domain\Patients\PatientSex;
use App\Modules\TenantManagement\Application\Contracts\LocationReferenceRepository;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PatientAttributeNormalizer
{
    public function __construct(
        private readonly PatientRepository $patientRepository,
        private readonly LocationReferenceRepository $locationReferenceRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     first_name: string,
     *     last_name: string,
     *     middle_name: string|null,
     *     preferred_name: string|null,
     *     sex: string,
     *     birth_date: string,
     *     national_id: string|null,
     *     email: string|null,
     *     phone: string|null,
     *     city_code: string|null,
     *     district_code: string|null,
     *     address_line_1: string|null,
     *     address_line_2: string|null,
     *     postal_code: string|null,
     *     notes: string|null
     * }
     */
    public function normalizeCreate(array $attributes, string $tenantId): array
    {
        $normalized = [
            'first_name' => $this->requiredTrimmedString($attributes['first_name'] ?? null),
            'last_name' => $this->requiredTrimmedString($attributes['last_name'] ?? null),
            'middle_name' => $this->nullableTrimmedString($attributes['middle_name'] ?? null),
            'preferred_name' => $this->nullableTrimmedString($attributes['preferred_name'] ?? null),
            'sex' => PatientSex::from($this->requiredTrimmedString($attributes['sex'] ?? null))->value,
            'birth_date' => CarbonImmutable::parse($this->requiredTrimmedString($attributes['birth_date'] ?? null))->toDateString(),
            'national_id' => $this->normalizedNationalId($attributes['national_id'] ?? null),
            'email' => $this->normalizedEmail($attributes['email'] ?? null),
            'phone' => $this->nullableTrimmedString($attributes['phone'] ?? null),
            'city_code' => $this->nullableTrimmedString($attributes['city_code'] ?? null),
            'district_code' => $this->nullableTrimmedString($attributes['district_code'] ?? null),
            'address_line_1' => $this->nullableTrimmedString($attributes['address_line_1'] ?? null),
            'address_line_2' => $this->nullableTrimmedString($attributes['address_line_2'] ?? null),
            'postal_code' => $this->nullableTrimmedString($attributes['postal_code'] ?? null),
            'notes' => $this->nullableTrimmedString($attributes['notes'] ?? null),
        ];

        $this->assertLocationConsistency($normalized['city_code'], $normalized['district_code']);
        $this->assertNationalIdAvailable($tenantId, $normalized['national_id']);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function normalizePatch(PatientData $patient, array $attributes): array
    {
        $updates = [];

        foreach ([
            'first_name',
            'last_name',
            'middle_name',
            'preferred_name',
            'sex',
            'birth_date',
            'national_id',
            'email',
            'phone',
            'city_code',
            'district_code',
            'address_line_1',
            'address_line_2',
            'postal_code',
            'notes',
        ] as $key) {
            if (! array_key_exists($key, $attributes)) {
                continue;
            }

            $normalized = match ($key) {
                'first_name', 'last_name' => $this->requiredTrimmedString($attributes[$key]),
                'middle_name', 'preferred_name', 'phone', 'city_code', 'district_code', 'address_line_1', 'address_line_2', 'postal_code', 'notes' => $this->nullableTrimmedString($attributes[$key]),
                'sex' => PatientSex::from($this->requiredTrimmedString($attributes[$key]))->value,
                'birth_date' => CarbonImmutable::parse($this->requiredTrimmedString($attributes[$key]))->toDateString(),
                'national_id' => $this->normalizedNationalId($attributes[$key]),
                'email' => $this->normalizedEmail($attributes[$key]),
            };

            if ($normalized !== $this->currentValue($patient, $key)) {
                $updates[$key] = $normalized;
            }
        }

        $cityCode = array_key_exists('city_code', $updates) ? $updates['city_code'] : $patient->cityCode;
        $districtCode = array_key_exists('district_code', $updates) ? $updates['district_code'] : $patient->districtCode;
        $this->assertLocationConsistency($cityCode, $districtCode);

        if (array_key_exists('national_id', $updates)) {
            $this->assertNationalIdAvailable($patient->tenantId, $updates['national_id'], $patient->patientId);
        }

        return $updates;
    }

    private function assertLocationConsistency(?string $cityCode, ?string $districtCode): void
    {
        if ($districtCode !== null && $cityCode === null) {
            throw new UnprocessableEntityHttpException('District codes require a city code.');
        }

        if ($cityCode !== null && ! $this->locationReferenceRepository->cityExists($cityCode)) {
            throw new UnprocessableEntityHttpException('The selected city does not exist in the approved location catalog.');
        }

        if ($districtCode === null) {
            return;
        }

        /** @var string $cityCode */
        $this->assertDistrictBelongsToCity($districtCode, $cityCode);
    }

    private function assertDistrictBelongsToCity(string $districtCode, string $cityCode): void
    {
        if (! $this->locationReferenceRepository->districtBelongsToCity($districtCode, $cityCode)) {
            throw new UnprocessableEntityHttpException('The selected district does not belong to the selected city.');
        }
    }

    private function assertNationalIdAvailable(string $tenantId, ?string $nationalId, ?string $ignorePatientId = null): void
    {
        if ($nationalId === null) {
            return;
        }

        if ($this->patientRepository->nationalIdExists($tenantId, $nationalId, $ignorePatientId)) {
            throw new ConflictHttpException('A patient with this national ID already exists in the active tenant.');
        }
    }

    private function currentValue(PatientData $patient, string $key): ?string
    {
        return match ($key) {
            'first_name' => $patient->firstName,
            'last_name' => $patient->lastName,
            'middle_name' => $patient->middleName,
            'preferred_name' => $patient->preferredName,
            'sex' => $patient->sex,
            'birth_date' => $patient->birthDate->toDateString(),
            'national_id' => $patient->nationalId,
            'email' => $patient->email,
            'phone' => $patient->phone,
            'city_code' => $patient->cityCode,
            'district_code' => $patient->districtCode,
            'address_line_1' => $patient->addressLine1,
            'address_line_2' => $patient->addressLine2,
            'postal_code' => $patient->postalCode,
            'notes' => $patient->notes,
            default => null,
        };
    }

    private function normalizedEmail(mixed $value): ?string
    {
        $normalized = $this->nullableTrimmedString($value);

        return $normalized !== null ? strtolower($normalized) : null;
    }

    private function normalizedNationalId(mixed $value): ?string
    {
        $normalized = $this->nullableTrimmedString($value);

        return $normalized !== null ? strtoupper($normalized) : null;
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
