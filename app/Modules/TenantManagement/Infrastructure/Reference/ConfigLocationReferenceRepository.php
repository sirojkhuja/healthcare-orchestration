<?php

namespace App\Modules\TenantManagement\Infrastructure\Reference;

use App\Modules\TenantManagement\Application\Contracts\LocationReferenceRepository;
use App\Modules\TenantManagement\Application\Data\LocationCityData;
use App\Modules\TenantManagement\Application\Data\LocationDistrictData;
use App\Modules\TenantManagement\Application\Data\LocationSearchResultData;

final class ConfigLocationReferenceRepository implements LocationReferenceRepository
{
    #[\Override]
    public function cityExists(string $cityCode): bool
    {
        foreach ($this->cities() as $city) {
            if ($this->stringValue($city['code'] ?? null) === $cityCode) {
                return true;
            }
        }

        return false;
    }

    #[\Override]
    public function districtBelongsToCity(string $districtCode, string $cityCode): bool
    {
        foreach ($this->cities() as $city) {
            if ($this->stringValue($city['code'] ?? null) !== $cityCode) {
                continue;
            }

            foreach ($this->districtsForCity($city) as $district) {
                if ($this->stringValue($district['code'] ?? null) === $districtCode) {
                    return true;
                }
            }
        }

        return false;
    }

    #[\Override]
    public function listCities(?string $search = null): array
    {
        $results = [];
        $needle = $this->normalizedSearch($search);

        foreach ($this->cities() as $city) {
            $code = $this->stringValue($city['code'] ?? null);
            $name = $this->stringValue($city['name'] ?? null);

            if ($code === '' || $name === '') {
                continue;
            }

            if ($needle !== null && ! str_contains(strtolower($name), $needle) && ! str_contains(strtolower($code), $needle)) {
                continue;
            }

            $results[] = new LocationCityData($code, $name);
        }

        usort($results, static fn (LocationCityData $left, LocationCityData $right): int => strcmp($left->name, $right->name));

        return $results;
    }

    #[\Override]
    public function listDistricts(string $cityCode): array
    {
        $results = [];

        foreach ($this->cities() as $city) {
            $currentCityCode = $this->stringValue($city['code'] ?? null);

            if ($currentCityCode !== $cityCode) {
                continue;
            }

            foreach ($this->districtsForCity($city) as $district) {
                $districtCode = $this->stringValue($district['code'] ?? null);
                $districtName = $this->stringValue($district['name'] ?? null);

                if ($districtCode === '' || $districtName === '') {
                    continue;
                }

                $results[] = new LocationDistrictData($districtCode, $currentCityCode, $districtName);
            }
        }

        usort($results, static fn (LocationDistrictData $left, LocationDistrictData $right): int => strcmp($left->name, $right->name));

        return $results;
    }

    #[\Override]
    public function search(string $query): array
    {
        $needle = $this->normalizedSearch($query) ?? '';
        $results = [];

        foreach ($this->cities() as $city) {
            $cityCode = $this->stringValue($city['code'] ?? null);
            $cityName = $this->stringValue($city['name'] ?? null);

            if ($cityCode === '' || $cityName === '') {
                continue;
            }

            if (str_contains(strtolower($cityName), $needle) || str_contains(strtolower($cityCode), $needle)) {
                $results[] = new LocationSearchResultData(
                    type: 'city',
                    code: $cityCode,
                    name: $cityName,
                    cityCode: $cityCode,
                    cityName: $cityName,
                );
            }

            foreach ($this->districtsForCity($city) as $district) {
                $districtCode = $this->stringValue($district['code'] ?? null);
                $districtName = $this->stringValue($district['name'] ?? null);

                if ($districtCode === '' || $districtName === '') {
                    continue;
                }

                if (! str_contains(strtolower($districtName), $needle) && ! str_contains(strtolower($districtCode), $needle)) {
                    continue;
                }

                $results[] = new LocationSearchResultData(
                    type: 'district',
                    code: $districtCode,
                    name: $districtName,
                    cityCode: $cityCode,
                    cityName: $cityName,
                );
            }
        }

        usort($results, static function (LocationSearchResultData $left, LocationSearchResultData $right): int {
            $leftKey = $left->type.'|'.($left->cityName ?? '').'|'.$left->name;
            $rightKey = $right->type.'|'.($right->cityName ?? '').'|'.$right->name;

            return strcmp($leftKey, $rightKey);
        });

        return $results;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cities(): array
    {
        $cities = config('locations.cities', []);

        if (! is_array($cities)) {
            return [];
        }

        $results = [];

        foreach ($cities as $city) {
            if (! is_array($city)) {
                continue;
            }

            /** @var array<string, mixed> $city */
            $results[] = $city;
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $city
     * @return list<array<string, mixed>>
     */
    private function districtsForCity(array $city): array
    {
        $districts = $city['districts'] ?? [];

        if (! is_array($districts)) {
            return [];
        }

        $results = [];

        foreach ($districts as $district) {
            if (! is_array($district)) {
                continue;
            }

            /** @var array<string, mixed> $district */
            $results[] = $district;
        }

        return $results;
    }

    private function normalizedSearch(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = strtolower(trim($value));

        return $trimmed !== '' ? $trimmed : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
