<?php

namespace App\Modules\IdentityAccess\Application\Data;

final readonly class ProfilePatchData
{
    public function __construct(
        public bool $nameProvided = false,
        public ?string $name = null,
        public bool $phoneProvided = false,
        public ?string $phone = null,
        public bool $jobTitleProvided = false,
        public ?string $jobTitle = null,
        public bool $localeProvided = false,
        public ?string $locale = null,
        public bool $timezoneProvided = false,
        public ?string $timezone = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->toDatabaseUpdates() === [];
    }

    /**
     * @return array<string, string|null>
     */
    public function toDatabaseUpdates(): array
    {
        $updates = [];

        if ($this->nameProvided && is_string($this->name) && $this->name !== '') {
            $updates['name'] = $this->name;
        }

        if ($this->phoneProvided) {
            $updates['phone'] = $this->phone;
        }

        if ($this->jobTitleProvided) {
            $updates['job_title'] = $this->jobTitle;
        }

        if ($this->localeProvided) {
            $updates['locale'] = $this->locale;
        }

        if ($this->timezoneProvided) {
            $updates['timezone'] = $this->timezone;
        }

        return $updates;
    }
}
