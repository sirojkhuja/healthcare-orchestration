<?php

use App\Shared\Infrastructure\Storage\FilesystemFileStorageManager;
use Illuminate\Support\Facades\Storage;

test('it rejects unsafe storage paths', function (): void {
    $manager = new FilesystemFileStorageManager;

    expect(fn () => $manager->storeExport('data', '../escape.txt'))
        ->toThrow(\InvalidArgumentException::class);
});

test('it writes exports to the configured exports disk', function (): void {
    Storage::fake('exports');

    $manager = new FilesystemFileStorageManager;
    $storedFile = $manager->storeExport('report', 'reports/daily.csv');

    Storage::disk('exports')->assertExists('reports/daily.csv');
    expect($storedFile->disk)->toBe('exports');
    expect($storedFile->path)->toBe('reports/daily.csv');
});
