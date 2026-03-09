<?php

namespace App\Modules\Provider\Application\Commands;

final readonly class RemoveProviderLicenseCommand
{
    public function __construct(
        public string $providerId,
        public string $licenseId,
    ) {}
}
