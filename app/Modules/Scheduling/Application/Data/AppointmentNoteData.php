<?php

namespace App\Modules\Scheduling\Application\Data;

use Carbon\CarbonImmutable;

final readonly class AppointmentNoteData
{
    public function __construct(
        public string $noteId,
        public string $appointmentId,
        public string $body,
        public string $authorUserId,
        public string $authorName,
        public string $authorEmail,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->noteId,
            'appointment_id' => $this->appointmentId,
            'body' => $this->body,
            'author' => [
                'user_id' => $this->authorUserId,
                'name' => $this->authorName,
                'email' => $this->authorEmail,
            ],
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
