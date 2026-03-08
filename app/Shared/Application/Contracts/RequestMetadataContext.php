<?php

namespace App\Shared\Application\Contracts;

use App\Shared\Application\Data\RequestMetadata;

interface RequestMetadataContext
{
    public function initialize(RequestMetadata $metadata): void;

    public function hasCurrent(): bool;

    public function current(): RequestMetadata;

    public function clear(): void;
}
