<?php

namespace App\Modules\Patient\Application\Commands;

use Illuminate\Http\UploadedFile;

final readonly class UploadPatientDocumentCommand
{
    public function __construct(
        public string $patientId,
        public UploadedFile $document,
        public ?string $title,
        public ?string $documentType,
    ) {}
}
