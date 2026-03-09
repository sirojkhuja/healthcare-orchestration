<?php

namespace App\Modules\Provider\Application\Data;

use App\Modules\TenantManagement\Application\Data\DepartmentData;
use App\Modules\TenantManagement\Application\Data\RoomData;

final readonly class ProviderProfileViewData
{
    public function __construct(
        public ProviderData $provider,
        public ?ProviderProfileData $profile,
        public ?DepartmentData $department,
        public ?RoomData $room,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge($this->provider->toArray(), [
            'professional_title' => $this->profile?->professionalTitle,
            'bio' => $this->profile?->bio,
            'years_of_experience' => $this->profile?->yearsOfExperience,
            'department_id' => $this->profile?->departmentId,
            'room_id' => $this->profile?->roomId,
            'is_accepting_new_patients' => $this->profile instanceof ProviderProfileData
                ? $this->profile->isAcceptingNewPatients
                : true,
            'languages' => $this->profile instanceof ProviderProfileData
                ? $this->profile->languages
                : [],
            'department' => $this->department?->toArray(),
            'room' => $this->room?->toArray(),
        ]);
    }
}
