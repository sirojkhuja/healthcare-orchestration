<?php

namespace App\Shared\Application\Contracts;

use App\Shared\Application\Data\StoredFile;
use Illuminate\Http\UploadedFile;

interface FileStorageManager
{
    public function storeAttachment(UploadedFile $file, string $path): StoredFile;

    public function storeExport(string $contents, string $path): StoredFile;

    public function storeArtifact(string $contents, string $path): StoredFile;
}
