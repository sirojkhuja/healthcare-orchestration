<?php

namespace App\Shared\Application\Data;

final readonly class MetricSeriesData
{
    /**
     * @param  array<string, string>  $labels
     */
    public function __construct(
        public array $labels,
        public float $value,
    ) {}
}
