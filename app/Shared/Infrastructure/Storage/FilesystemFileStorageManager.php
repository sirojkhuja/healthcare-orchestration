<?php

namespace App\Shared\Infrastructure\Storage;

use App\Shared\Application\Contracts\FileStorageManager;
use App\Shared\Application\Data\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class FilesystemFileStorageManager implements FileStorageManager
{
    #[\Override]
    public function storeAttachment(UploadedFile $file, string $path): StoredFile
    {
        $storedPath = $file->store($this->normalizePath($path), 'attachments');

        if ($storedPath === false) {
            throw new InvalidArgumentException('Failed to persist the uploaded attachment.');
        }

        return new StoredFile('attachments', $storedPath);
    }

    #[\Override]
    public function storeExport(string $contents, string $path): StoredFile
    {
        return $this->write('exports', $contents, $path);
    }

    #[\Override]
    public function storeArtifact(string $contents, string $path): StoredFile
    {
        return $this->write('artifacts', $contents, $path);
    }

    private function write(string $disk, string $contents, string $path): StoredFile
    {
        $normalizedPath = $this->normalizePath($path);
        Storage::disk($disk)->put($normalizedPath, $contents);

        return new StoredFile($disk, $normalizedPath);
    }

    private function normalizePath(string $path): string
    {
        $normalizedPath = trim($path, '/');

        if ($normalizedPath === '' || str_contains($normalizedPath, '..')) {
            throw new InvalidArgumentException('Storage paths must stay within the configured disk root.');
        }

        return $normalizedPath;
    }
}
