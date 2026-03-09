<?php

namespace App\Modules\TenantManagement\Application\Commands;

final readonly class UpdateRoomCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $clinicId,
        public string $roomId,
        public array $attributes,
    ) {}
}
