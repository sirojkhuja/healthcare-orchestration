<?php

namespace App\Shared\Application\Data;

final readonly class StoredFile
{
    public function __construct(
        public string $disk,
        public string $path,
        public string $visibility = 'private',
    ) {}
}
