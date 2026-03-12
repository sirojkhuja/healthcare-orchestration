<?php

namespace App\Modules\Reporting\Application\Commands;

final readonly class DeleteReportCommand
{
    public function __construct(
        public string $reportId,
    ) {}
}
