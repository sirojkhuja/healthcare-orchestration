<?php

namespace App\Modules\Notifications\Application\Commands;

final readonly class TestRenderTemplateCommand
{
    /**
     * @param  array<string, mixed>  $variables
     */
    public function __construct(
        public string $templateId,
        public array $variables,
    ) {}
}
