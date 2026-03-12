<?php

namespace App\Shared\Application\Data;

final readonly class MetricHistogramData
{
    /**
     * @param  array<string, string>  $labels
     * @param  array<string, int>  $buckets
     */
    public function __construct(
        public array $labels,
        public int $count,
        public float $sum,
        public array $buckets,
    ) {}
}
