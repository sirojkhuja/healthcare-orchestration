<?php

namespace App\Modules\IdentityAccess\Infrastructure\Auth;

use App\Models\User;
use App\Modules\IdentityAccess\Application\Contracts\IdentityUserProvider;
use App\Modules\IdentityAccess\Application\Data\AuthenticatedUserData;
use Illuminate\Support\Facades\Hash;

final class EloquentIdentityUserProvider implements IdentityUserProvider
{
    #[\Override]
    public function attempt(string $email, string $password): ?AuthenticatedUserData
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $this->toData($user);
    }

    #[\Override]
    public function findById(string $userId): ?AuthenticatedUserData
    {
        $user = User::query()->find($userId);

        return $user instanceof User ? $this->toData($user) : null;
    }

    private function toData(User $user): AuthenticatedUserData
    {
        /** @var mixed $id */
        $id = $user->getAttribute('id');

        return new AuthenticatedUserData(
            id: is_string($id) ? $id : '',
            name: $user->name,
            email: $user->email,
        );
    }
}
