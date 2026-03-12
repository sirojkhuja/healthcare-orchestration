<?php

namespace App\Modules\Observability\Application\Commands;

final readonly class SetFeatureFlagsCommand
{
    /**
     * @param  array<string, bool>  $flags
     */
    public function __construct(public array $flags) {}
}
