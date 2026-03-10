<?php

namespace App\Modules\Pharmacy\Application\Data;

use Carbon\CarbonImmutable;

final readonly class PrescriptionExportData
{
    public function __construct(
        public string $exportId,
        public string $format,
        public string $fileName,
        public int $rowCount,
        public CarbonImmutable $generatedAt,
        public PrescriptionSearchCriteria $filters,
        public string $disk,
        public string $path,
        public string $visibility = 'private',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'export_id' => $this->exportId,
            'format' => $this->format,
            'file_name' => $this->fileName,
            'row_count' => $this->rowCount,
            'generated_at' => $this->generatedAt->toIso8601String(),
            'filters' => $this->filters->toArray(),
            'storage' => [
                'disk' => $this->disk,
                'path' => $this->path,
                'visibility' => $this->visibility,
            ],
        ];
    }
}
