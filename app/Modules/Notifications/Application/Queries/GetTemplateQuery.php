<?php

namespace App\Modules\Notifications\Application\Queries;

final readonly class GetTemplateQuery
{
    public function __construct(
        public string $templateId,
    ) {}
}
