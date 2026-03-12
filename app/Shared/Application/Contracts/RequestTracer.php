<?php

namespace App\Shared\Application\Contracts;

interface RequestTracer
{
    /**
     * @param  array<string, array<int, bool|int|float|string|null>|bool|int|float|string|null>  $attributes
     */
    public function startServerSpan(string $name, array $attributes = []): RequestTraceSpan;
}
