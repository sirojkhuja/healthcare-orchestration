<?php

namespace App\Modules\Patient\Application\Commands;

final readonly class SetPatientTagsCommand
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public string $patientId,
        public array $tags,
    ) {}
}
