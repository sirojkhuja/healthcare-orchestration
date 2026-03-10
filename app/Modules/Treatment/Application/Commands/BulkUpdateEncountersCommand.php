<?php

namespace App\Modules\Treatment\Application\Commands;

final readonly class BulkUpdateEncountersCommand
{
    /**
     * @param  list<string>  $encounterIds
     * @param  array{
     *     status?: string,
     *     provider_id?: string,
     *     clinic_id?: ?string,
     *     room_id?: ?string,
     *     encountered_at?: string,
     *     timezone?: string
     * }  $changes
     */
    public function __construct(
        public array $encounterIds,
        public array $changes,
    ) {}
}
