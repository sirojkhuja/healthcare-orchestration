<?php

test('every configured module has the expected clean architecture directories', function (): void {
    collect(config('medflow.modules'))
        ->each(function (string $module): void {
            $basePath = app_path("Modules/{$module}");

            expect($basePath)->toBeDirectory();
            expect("{$basePath}/Domain")->toBeDirectory();
            expect("{$basePath}/Application")->toBeDirectory();
            expect("{$basePath}/Infrastructure")->toBeDirectory();
            expect("{$basePath}/Presentation")->toBeDirectory();
        });
});
