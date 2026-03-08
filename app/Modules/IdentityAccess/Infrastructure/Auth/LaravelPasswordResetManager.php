<?php

namespace App\Modules\IdentityAccess\Infrastructure\Auth;

use App\Models\User;
use App\Modules\IdentityAccess\Application\Contracts\PasswordResetManager;
use App\Modules\IdentityAccess\Application\Data\AuthenticatedUserData;
use Illuminate\Auth\Passwords\PasswordBroker as LaravelPasswordBroker;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final class LaravelPasswordResetManager implements PasswordResetManager
{
    #[\Override]
    public function issueToken(string $email): void
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            return;
        }

        $broker = $this->broker();
        $broker->deleteToken($user);
        $broker->createToken($user);
    }

    #[\Override]
    public function reset(string $email, string $token, string $password): ?AuthenticatedUserData
    {
        $resolvedUser = null;
        $status = $this->broker()->reset([
            'email' => $email,
            'token' => $token,
            'password' => $password,
            'password_confirmation' => $password,
        ], function (User $user, string $password) use (&$resolvedUser): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            $userId = $user->getAuthIdentifier();
            assert(is_string($userId));

            $resolvedUser = new AuthenticatedUserData(
                id: $userId,
                name: $user->name,
                email: $user->email,
            );
        });

        return $status === Password::PASSWORD_RESET ? $resolvedUser : null;
    }

    private function broker(): LaravelPasswordBroker
    {
        $broker = Password::broker('users');
        assert($broker instanceof LaravelPasswordBroker);

        return $broker;
    }
}
