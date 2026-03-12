<?php

namespace App\Modules\Observability\Application\Data;

final readonly class VersionData
{
    /**
     * @param  list<string>  $modules
     */
    public function __construct(
        public string $service,
        public string $environment,
        public string $version,
        public string $phpVersion,
        public string $laravelVersion,
        public array $modules,
        public ?string $gitSha,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'service' => $this->service,
            'environment' => $this->environment,
            'version' => $this->version,
            'php_version' => $this->phpVersion,
            'laravel_version' => $this->laravelVersion,
            'modules' => $this->modules,
            'git_sha' => $this->gitSha,
        ];
    }
}
