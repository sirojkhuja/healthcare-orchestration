<?php

declare(strict_types=1);

use Illuminate\Support\Str;

function runReleaseCommand(array $command): array
{
    $escaped = array_map(
        static fn (string $argument): string => escapeshellarg($argument),
        $command,
    );

    $descriptorSpec = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open(
        implode(' ', $escaped),
        $descriptorSpec,
        $pipes,
        base_path(),
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start release automation command.');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    return [$exitCode, trim($stdout.PHP_EOL.$stderr)];
}

it('builds a release changelog artifact from git history', function (): void {
    $tempFile = storage_path('framework/testing/'.Str::uuid().'-release-notes.md');

    [$exitCode, $output] = runReleaseCommand([
        'bash',
        'scripts/release/build-changelog.sh',
        '--version',
        '0.1.0-rc.1',
        '--output',
        $tempFile,
    ]);

    expect($exitCode)->toBe(0);
    expect(is_file($tempFile))->toBeTrue();
    expect(file_get_contents($tempFile))
        ->toContain('# Release Notes')
        ->toContain('Version: `0.1.0-rc.1`');

    @unlink($tempFile);
});

it('runs a release dry run with skipped verify and writes artifacts', function (): void {
    $outputDir = storage_path('framework/testing/'.Str::uuid().'-release-dry-run');

    [$exitCode, $output] = runReleaseCommand([
        'bash',
        'scripts/release/dry-run.sh',
        '--version',
        '0.1.0-rc.1',
        '--skip-verify',
        '--output-dir',
        $outputDir,
    ]);

    expect($exitCode)->toBe(0);
    expect($output)->toContain('Release dry run passed for 0.1.0-rc.1');
    expect(is_file($outputDir.'/CHANGELOG-0.1.0-rc.1.md'))->toBeTrue();
    expect(is_file($outputDir.'/release-manifest.json'))->toBeTrue();
});
