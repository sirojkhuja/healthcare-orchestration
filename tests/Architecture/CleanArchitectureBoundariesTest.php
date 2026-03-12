<?php

it('keeps domain code free from framework and lower-layer imports', function (): void {
    $violations = architectureImportViolations(
        basePaths: [app_path('Modules'), app_path('Shared')],
        requiredSegment: DIRECTORY_SEPARATOR.'Domain'.DIRECTORY_SEPARATOR,
        forbiddenPrefixes: ['Illuminate\\', 'Laravel\\'],
        forbiddenFragments: ['\\Infrastructure\\', '\\Presentation\\'],
    );

    expect($violations)->toBe([]);
});

it('keeps application code free from presentation and infrastructure imports', function (): void {
    $violations = architectureImportViolations(
        basePaths: [app_path('Modules'), app_path('Shared')],
        requiredSegment: DIRECTORY_SEPARATOR.'Application'.DIRECTORY_SEPARATOR,
        forbiddenFragments: ['\\Infrastructure\\', '\\Presentation\\'],
        forbiddenPrefixes: [],
        includeImport: static fn (string $import): bool => str_starts_with($import, 'App\\'),
    );

    expect($violations)->toBe([]);
});

it('keeps presentation code free from module infrastructure imports', function (): void {
    $violations = architectureImportViolations(
        basePaths: [app_path('Modules')],
        requiredSegment: DIRECTORY_SEPARATOR.'Presentation'.DIRECTORY_SEPARATOR,
        forbiddenPrefixes: [],
        forbiddenFragments: ['\\Infrastructure\\'],
        includeImport: static fn (string $import): bool => str_starts_with($import, 'App\\Modules\\'),
    );

    expect($violations)->toBe([]);
});

/**
 * @param  list<string>  $basePaths
 * @param  list<string>  $forbiddenPrefixes
 * @param  list<string>  $forbiddenFragments
 * @param  (callable(string): bool)|null  $includeImport
 * @return list<string>
 */
function architectureImportViolations(
    array $basePaths,
    string $requiredSegment,
    array $forbiddenPrefixes,
    array $forbiddenFragments,
    ?callable $includeImport = null,
): array {
    $violations = [];

    foreach (architecturePhpFiles($basePaths, $requiredSegment) as $path) {
        foreach (architectureImports($path) as $import) {
            if ($includeImport !== null && ! $includeImport($import)) {
                continue;
            }

            foreach ($forbiddenPrefixes as $prefix) {
                if (str_starts_with($import, $prefix)) {
                    $violations[] = sprintf('%s imports %s', architectureRelativePath($path), $import);

                    continue 2;
                }
            }

            foreach ($forbiddenFragments as $fragment) {
                if (str_contains($import, $fragment)) {
                    $violations[] = sprintf('%s imports %s', architectureRelativePath($path), $import);

                    continue 2;
                }
            }
        }
    }

    sort($violations);

    return $violations;
}

/**
 * @param  list<string>  $basePaths
 * @return list<string>
 */
function architecturePhpFiles(array $basePaths, string $requiredSegment): array
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

            $path = $file->getPathname();

            if (! str_contains($path, $requiredSegment)) {
                continue;
            }

            $paths[] = $path;
        }
    }

    sort($paths);

    return $paths;
}

/**
 * @return list<string>
 */
function architectureImports(string $path): array
{
    $contents = file_get_contents($path);

    if (! is_string($contents) || $contents === '') {
        return [];
    }

    preg_match_all('/^use\s+([^;]+);$/m', $contents, $matches);

    $imports = array_map(static fn (string $import): string => trim($import), $matches[1] ?? []);

    sort($imports);

    return $imports;
}

function architectureRelativePath(string $path): string
{
    return ltrim(str_replace(base_path(), '', $path), DIRECTORY_SEPARATOR);
}
