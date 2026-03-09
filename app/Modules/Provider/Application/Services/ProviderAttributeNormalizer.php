<?php

namespace App\Modules\Provider\Application\Services;

use App\Modules\Provider\Application\Data\ProviderData;
use App\Modules\Provider\Domain\Providers\ProviderType;
use App\Modules\TenantManagement\Application\Contracts\ClinicRepository;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ProviderAttributeNormalizer
{
    public function __construct(
        private readonly ClinicRepository $clinicRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     first_name: string,
     *     last_name: string,
     *     middle_name: ?string,
     *     preferred_name: ?string,
     *     provider_type: string,
     *     email: ?string,
     *     phone: ?string,
     *     clinic_id: ?string,
     *     notes: ?string
     * }
     */
    public function normalizeCreate(array $attributes, string $tenantId): array
    {
        $normalized = [
            'first_name' => $this->requiredTrimmedString($attributes['first_name'] ?? null),
            'last_name' => $this->requiredTrimmedString($attributes['last_name'] ?? null),
            'middle_name' => $this->nullableTrimmedString($attributes['middle_name'] ?? null),
            'preferred_name' => $this->nullableTrimmedString($attributes['preferred_name'] ?? null),
            'provider_type' => ProviderType::from($this->requiredTrimmedString($attributes['provider_type'] ?? null))->value,
            'email' => $this->normalizedEmail($attributes['email'] ?? null),
            'phone' => $this->nullableTrimmedString($attributes['phone'] ?? null),
            'clinic_id' => $this->nullableTrimmedString($attributes['clinic_id'] ?? null),
            'notes' => $this->nullableTrimmedString($attributes['notes'] ?? null),
        ];

        $this->assertClinicExists($tenantId, $normalized['clinic_id']);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function normalizePatch(ProviderData $provider, array $attributes): array
    {
        $updates = [];

        foreach ([
            'first_name',
            'last_name',
            'middle_name',
            'preferred_name',
            'provider_type',
            'email',
            'phone',
            'clinic_id',
            'notes',
        ] as $key) {
            if (! array_key_exists($key, $attributes)) {
                continue;
            }

            $normalized = match ($key) {
                'first_name', 'last_name' => $this->requiredTrimmedString($attributes[$key]),
                'middle_name', 'preferred_name', 'phone', 'clinic_id', 'notes' => $this->nullableTrimmedString($attributes[$key]),
                'provider_type' => ProviderType::from($this->requiredTrimmedString($attributes[$key]))->value,
                'email' => $this->normalizedEmail($attributes[$key]),
            };

            if ($normalized !== $this->currentValue($provider, $key)) {
                $updates[$key] = $normalized;
            }
        }

        if (array_key_exists('clinic_id', $updates)) {
            $this->assertClinicExists($provider->tenantId, $updates['clinic_id']);
        }

        return $updates;
    }

    private function assertClinicExists(string $tenantId, ?string $clinicId): void
    {
        if ($clinicId === null) {
            return;
        }

        if (! $this->clinicRepository->findClinic($tenantId, $clinicId)) {
            throw new UnprocessableEntityHttpException('The clinic_id field must reference an existing clinic in the current tenant.');
        }
    }

    private function currentValue(ProviderData $provider, string $key): ?string
    {
        return match ($key) {
            'first_name' => $provider->firstName,
            'last_name' => $provider->lastName,
            'middle_name' => $provider->middleName,
            'preferred_name' => $provider->preferredName,
            'provider_type' => $provider->providerType,
            'email' => $provider->email,
            'phone' => $provider->phone,
            'clinic_id' => $provider->clinicId,
            'notes' => $provider->notes,
            default => null,
        };
    }

    private function normalizedEmail(mixed $value): ?string
    {
        $normalized = $this->nullableTrimmedString($value);

        return $normalized !== null ? strtolower($normalized) : null;
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
