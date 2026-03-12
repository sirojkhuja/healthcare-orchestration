<?php

namespace App\Modules\Notifications\Application\Contracts;

use App\Modules\Notifications\Application\Data\EmailProviderSettingsData;

interface EmailProviderSettingsRepository
{
    public function get(string $tenantId): EmailProviderSettingsData;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(string $tenantId, array $attributes): EmailProviderSettingsData;
}
