<?php

namespace App\Modules\IdentityAccess\Application\Data;

final readonly class BulkUserActionResultData
{
    /**
     * @param  list<ManagedUserData>  $users
     */
    public function __construct(
        public string $action,
        public int $affectedCount,
        public array $users,
    ) {}

    /**
     * @return array{
     *     action: string,
     *     affected_count: int,
     *     users: list<array{
     *         id: string,
     *         tenant_id: string,
     *         name: string,
     *         email: string,
     *         status: string,
     *         email_verified_at: string|null,
     *         created_at: string,
     *         updated_at: string,
     *         joined_at: string,
     *         membership_updated_at: string
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'affected_count' => $this->affectedCount,
            'users' => array_map(
                static fn (ManagedUserData $user): array => $user->toArray(),
                $this->users,
            ),
        ];
    }
}
