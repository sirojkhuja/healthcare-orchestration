<?php

namespace App\Modules\Scheduling\Domain\Appointments;

use InvalidArgumentException;

final readonly class AppointmentActor
{
    public function __construct(
        public string $type,
        public ?string $id,
        public ?string $name,
    ) {
        if (trim($this->type) === '') {
            throw new InvalidArgumentException('Appointment actor type is required.');
        }
    }

    /**
     * @return array{type: string, id: string|null, name: string|null}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
