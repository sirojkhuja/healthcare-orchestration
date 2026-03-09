<?php

namespace App\Modules\TenantManagement\Application\Contracts;

use App\Modules\TenantManagement\Application\Data\LocationCityData;
use App\Modules\TenantManagement\Application\Data\LocationDistrictData;
use App\Modules\TenantManagement\Application\Data\LocationSearchResultData;

interface LocationReferenceRepository
{
    public function cityExists(string $cityCode): bool;

    public function districtBelongsToCity(string $districtCode, string $cityCode): bool;

    /**
     * @return list<LocationCityData>
     */
    public function listCities(?string $search = null): array;

    /**
     * @return list<LocationDistrictData>
     */
    public function listDistricts(string $cityCode): array;

    /**
     * @return list<LocationSearchResultData>
     */
    public function search(string $query): array;
}
