<?php

namespace App\Modules\Provider\Infrastructure\Persistence;

use App\Modules\Provider\Application\Contracts\ProviderProfileRepository;
use App\Modules\Provider\Application\Data\ProviderProfileData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use stdClass;

final class DatabaseProviderProfileRepository implements ProviderProfileRepository
{
    #[\Override]
    public function clearLocationFields(string $tenantId, string $providerId): void
    {
        DB::table('provider_profiles')
            ->where('tenant_id', $tenantId)
            ->where('provider_id', $providerId)
            ->update([
                'department_id' => null,
                'room_id' => null,
                'updated_at' => CarbonImmutable::now(),
            ]);
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $providerId): ?ProviderProfileData
    {
        $row = DB::table('provider_profiles')
            ->where('tenant_id', $tenantId)
            ->where('provider_id', $providerId)
            ->first();

        return $row instanceof stdClass ? $this->map($row) : null;
    }

    #[\Override]
    public function upsert(string $tenantId, string $providerId, array $attributes): ProviderProfileData
    {
        $existing = $this->findInTenant($tenantId, $providerId);
        $now = CarbonImmutable::now();

        DB::table('provider_profiles')->updateOrInsert(
            ['provider_id' => $providerId],
            [
                'tenant_id' => $tenantId,
                'professional_title' => $attributes['professional_title'],
                'bio' => $attributes['bio'],
                'years_of_experience' => $attributes['years_of_experience'],
                'department_id' => $attributes['department_id'],
                'room_id' => $attributes['room_id'],
                'is_accepting_new_patients' => $attributes['is_accepting_new_patients'],
                'languages' => json_encode($attributes['languages'], JSON_THROW_ON_ERROR),
                'created_at' => $existing instanceof ProviderProfileData ? $existing->createdAt : $now,
                'updated_at' => $now,
            ],
        );

        return $this->findInTenant($tenantId, $providerId)
            ?? throw new \LogicException('Provider profile could not be reloaded.');
    }

    private function map(stdClass $row): ProviderProfileData
    {
        return new ProviderProfileData(
            providerId: $this->stringValue($row->provider_id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            professionalTitle: $this->nullableString($row->professional_title ?? null),
            bio: $this->nullableString($row->bio ?? null),
            yearsOfExperience: is_numeric($row->years_of_experience ?? null) ? (int) $row->years_of_experience : null,
            departmentId: $this->nullableString($row->department_id ?? null),
            roomId: $this->nullableString($row->room_id ?? null),
            isAcceptingNewPatients: (bool) ($row->is_accepting_new_patients ?? true),
            languages: $this->languages($row->languages ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }

    private function dateTime(mixed $value): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse($this->stringValue($value));
    }

    /**
     * @return list<string>
     */
    private function languages(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            /** @var mixed $decoded */
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

            if (is_array($decoded)) {
                return array_values(array_filter($decoded, 'is_string'));
            }
        }

        if (is_array($value)) {
            return array_values(array_filter($value, 'is_string'));
        }

        return [];
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
