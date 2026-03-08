<?php

namespace App\Modules\IdentityAccess\Application\Data;

final readonly class PermissionDefinitionData
{
    public function __construct(
        public string $name,
        public string $groupKey,
        public string $groupName,
        public string $groupDescription,
        public string $description,
    ) {}

    /**
     * @return array{
     *     name: string,
     *     group_key: string,
     *     group_name: string,
     *     group_description: string,
     *     description: string
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'group_key' => $this->groupKey,
            'group_name' => $this->groupName,
            'group_description' => $this->groupDescription,
            'description' => $this->description,
        ];
    }
}
