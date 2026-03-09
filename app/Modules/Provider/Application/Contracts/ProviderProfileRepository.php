<?php

namespace App\Modules\Provider\Application\Contracts;

use App\Modules\Provider\Application\Data\ProviderProfileData;

interface ProviderProfileRepository
{
    public function clearLocationFields(string $tenantId, string $providerId): void;

    public function findInTenant(string $tenantId, string $providerId): ?ProviderProfileData;

    /**
     * @param  array{
     *      professional_title: ?string,
     *      bio: ?string,
     *      years_of_experience: ?int,
     *      department_id: ?string,
     *      room_id: ?string,
     *      is_accepting_new_patients: bool,
     *      languages: list<string>
     *  }  $attributes
     */
    public function upsert(string $tenantId, string $providerId, array $attributes): ProviderProfileData;
}
