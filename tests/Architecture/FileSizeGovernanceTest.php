<?php

it('keeps application php files within the documented hard limit or a reviewed exception register', function (): void {
    $limit = (int) config('governance.architecture.file_size.hard_limit', 400);
    /** @var array<string, string> $reviewedExceptions */
    $reviewedExceptions = config('governance.architecture.file_size.reviewed_exceptions', []);

    $noLongerNeeded = [];
    $violations = [];

    foreach ($reviewedExceptions as $path => $reason) {
        $fullPath = base_path($path);

        expect($fullPath)->toBeFile(sprintf('Missing reviewed exception file: %s (%s)', $path, $reason));

        $lineCount = fileLineCount($fullPath);

        if ($lineCount <= $limit) {
            $noLongerNeeded[] = sprintf('%s is now %d lines and should be removed from the exception register', $path, $lineCount);
        }
    }

    foreach (governancePhpFiles([app_path()]) as $path) {
        $relativePath = ltrim(str_replace(base_path(), '', $path), DIRECTORY_SEPARATOR);
        $lineCount = fileLineCount($path);

        if ($lineCount <= $limit || array_key_exists($relativePath, $reviewedExceptions)) {
            continue;
        }

        $violations[] = sprintf('%s is %d lines', $relativePath, $lineCount);
    }

    sort($noLongerNeeded);
    sort($violations);

    expect($noLongerNeeded)->toBe([]);
    expect($violations)->toBe([]);
});

/**
 * @param  list<string>  $basePaths
 * @return list<string>
 */
function governancePhpFiles(array $basePaths): array
{
    $paths = [];

    foreach ($basePaths as $basePath) {
        if (! is_dir($basePath)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $paths[] = $file->getPathname();
        }
    }

    sort($paths);

    return $paths;
}

function fileLineCount(string $path): int
{
    $lines = file($path, FILE_IGNORE_NEW_LINES);

    return is_array($lines) ? count($lines) : 0;
}
