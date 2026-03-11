<?php

namespace App\Modules\Notifications\Application\Commands;

final readonly class UpdateTemplateCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $templateId,
        public array $attributes,
    ) {}
}
