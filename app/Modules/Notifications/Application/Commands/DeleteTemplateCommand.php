<?php

namespace App\Modules\Notifications\Application\Commands;

final readonly class DeleteTemplateCommand
{
    public function __construct(
        public string $templateId,
    ) {}
}
