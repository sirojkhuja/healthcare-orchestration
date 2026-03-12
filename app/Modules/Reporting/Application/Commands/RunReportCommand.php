<?php

namespace App\Modules\Reporting\Application\Commands;

final readonly class RunReportCommand
{
    public function __construct(
        public string $reportId,
    ) {}
}
