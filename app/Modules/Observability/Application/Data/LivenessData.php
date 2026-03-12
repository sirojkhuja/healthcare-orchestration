<?php

namespace App\Modules\Observability\Application\Data;

use Carbon\CarbonImmutable;

final readonly class LivenessData
{
    public function __construct(
        public string $status,
        public string $service,
        public string $version,
        public CarbonImmutable $checkedAt,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'service' => $this->service,
            'version' => $this->version,
            'checked_at' => $this->checkedAt->toIso8601String(),
        ];
    }
}
