<?php

namespace App\Modules\Provider\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Provider\Application\Contracts\ProviderProfileRepository;
use App\Modules\Provider\Application\Contracts\ProviderRepository;
use App\Modules\Provider\Application\Data\ProviderData;
use App\Modules\Provider\Application\Data\ProviderProfileData;
use App\Modules\Provider\Application\Data\ProviderProfileViewData;
use App\Modules\TenantManagement\Application\Contracts\ClinicRepository;
use App\Modules\TenantManagement\Application\Data\DepartmentData;
use App\Modules\TenantManagement\Application\Data\RoomData;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ProviderProfileService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ProviderRepository $providerRepository,
        private readonly ProviderProfileRepository $providerProfileRepository,
        private readonly ClinicRepository $clinicRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function get(string $providerId): ProviderProfileViewData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $provider = $this->providerOrFail($providerId);
        $profile = $this->providerProfileRepository->findInTenant($tenantId, $providerId);

        return $this->buildView($provider, $profile);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $providerId, array $attributes): ProviderProfileViewData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $provider = $this->providerOrFail($providerId);
        $current = $this->providerProfileRepository->findInTenant($tenantId, $providerId);
        $before = $this->buildView($provider, $current);
        $normalized = $this->normalizeState($provider, $current, $attributes);
        $profile = $this->providerProfileRepository->upsert($tenantId, $providerId, $normalized);
        $after = $this->buildView($provider, $profile);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'providers.profile_updated',
            objectType: 'provider',
            objectId: $providerId,
            before: $before->toArray(),
            after: $after->toArray(),
        ));

        return $after;
    }

    private function buildView(ProviderData $provider, ?ProviderProfileData $profile): ProviderProfileViewData
    {
        $department = $this->department($provider, $profile);
        $room = $this->room($provider, $profile);

        return new ProviderProfileViewData($provider, $profile, $department, $room);
    }

    private function department(ProviderData $provider, ?ProviderProfileData $profile): ?DepartmentData
    {
        $departmentId = $profile?->departmentId;

        if ($provider->clinicId === null || $departmentId === null) {
            return null;
        }

        return $this->clinicRepository->findDepartment(
            $this->tenantContext->requireTenantId(),
            $provider->clinicId,
            $departmentId,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     professional_title: ?string,
     *     bio: ?string,
     *     years_of_experience: ?int,
     *     department_id: ?string,
     *     room_id: ?string,
     *     is_accepting_new_patients: bool,
     *     languages: list<string>
     * }
     */
    private function normalizeState(ProviderData $provider, ?ProviderProfileData $current, array $attributes): array
    {
        $state = [
            'professional_title' => $this->stringOrCurrent($attributes, 'professional_title', $current?->professionalTitle),
            'bio' => $this->stringOrCurrent($attributes, 'bio', $current?->bio),
            'years_of_experience' => $this->intOrCurrent($attributes, 'years_of_experience', $current?->yearsOfExperience),
            'department_id' => $this->uuidOrCurrent($attributes, 'department_id', $current?->departmentId),
            'room_id' => $this->uuidOrCurrent($attributes, 'room_id', $current?->roomId),
            'is_accepting_new_patients' => $this->boolOrCurrent(
                $attributes,
                'is_accepting_new_patients',
                $current instanceof ProviderProfileData ? $current->isAcceptingNewPatients : true,
            ),
            'languages' => array_key_exists('languages', $attributes)
                ? $this->normalizeLanguages($attributes['languages'])
                : ($current instanceof ProviderProfileData ? $current->languages : []),
        ];

        $this->assertLocationConsistency($provider, $state['department_id'], $state['room_id']);

        return $state;
    }

    private function providerOrFail(string $providerId): ProviderData
    {
        $provider = $this->providerRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $providerId,
        );

        if (! $provider instanceof ProviderData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $provider;
    }

    private function room(ProviderData $provider, ?ProviderProfileData $profile): ?RoomData
    {
        $roomId = $profile?->roomId;

        if ($provider->clinicId === null || $roomId === null) {
            return null;
        }

        return $this->clinicRepository->findRoom(
            $this->tenantContext->requireTenantId(),
            $provider->clinicId,
            $roomId,
        );
    }

    private function assertLocationConsistency(
        ProviderData $provider,
        ?string $departmentId,
        ?string $roomId,
    ): void {
        if ($departmentId === null && $roomId === null) {
            return;
        }

        if ($provider->clinicId === null) {
            throw new UnprocessableEntityHttpException('Provider clinic assignment is required before setting department or room.');
        }

        $tenantId = $this->tenantContext->requireTenantId();
        $department = null;

        if ($departmentId !== null) {
            $department = $this->clinicRepository->findDepartment($tenantId, $provider->clinicId, $departmentId);

            if (! $department instanceof DepartmentData) {
                throw new UnprocessableEntityHttpException('The selected department does not belong to the provider clinic.');
            }
        }

        if ($roomId === null) {
            return;
        }

        $room = $this->clinicRepository->findRoom($tenantId, $provider->clinicId, $roomId);

        if (! $room instanceof RoomData) {
            throw new UnprocessableEntityHttpException('The selected room does not belong to the provider clinic.');
        }

        if (
            $department instanceof DepartmentData
            && $room->departmentId !== null
            && $room->departmentId !== $department->departmentId
        ) {
            throw new UnprocessableEntityHttpException('The selected room does not belong to the selected department.');
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function boolOrCurrent(array $attributes, string $key, bool $current): bool
    {
        return array_key_exists($key, $attributes) ? (bool) $attributes[$key] : $current;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function intOrCurrent(array $attributes, string $key, ?int $current): ?int
    {
        if (! array_key_exists($key, $attributes) || $attributes[$key] === null) {
            return array_key_exists($key, $attributes) ? null : $current;
        }

        if (is_int($attributes[$key])) {
            return $attributes[$key];
        }

        if (is_numeric($attributes[$key])) {
            return (int) $attributes[$key];
        }

        return $current;
    }

    /**
     * @return list<string>
     */
    private function normalizeLanguages(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $language) {
            if (! is_string($language)) {
                continue;
            }

            $trimmed = preg_replace('/\s+/', ' ', trim($language));

            if (! is_string($trimmed) || $trimmed === '') {
                continue;
            }

            $normalized[mb_strtolower($trimmed)] = $trimmed;
        }

        uasort($normalized, static fn (string $left, string $right): int => strcasecmp($left, $right));

        return array_values($normalized);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function stringOrCurrent(array $attributes, string $key, ?string $current): ?string
    {
        if (! array_key_exists($key, $attributes)) {
            return $current;
        }

        if (! is_string($attributes[$key])) {
            return null;
        }

        $normalized = trim($attributes[$key]);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function uuidOrCurrent(array $attributes, string $key, ?string $current): ?string
    {
        if (! array_key_exists($key, $attributes)) {
            return $current;
        }

        return is_string($attributes[$key]) && $attributes[$key] !== '' ? $attributes[$key] : null;
    }
}
