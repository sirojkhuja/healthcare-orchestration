<?php

namespace App\Modules\IdentityAccess\Infrastructure\Auth;

use App\Models\User;
use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\IdentityAccess\Application\Data\AuthenticatedRequestData;
use App\Modules\IdentityAccess\Application\Data\AuthenticatedUserData;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;

final class LaravelAuthenticatedRequestContext implements AuthenticatedRequestContext
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly Request $request,
    ) {}

    #[\Override]
    public function current(): AuthenticatedRequestData
    {
        $user = $this->auth->guard('api')->user();
        $sessionId = $this->request->attributes->get('auth_session_id');

        if (! $user instanceof User || ! is_string($sessionId) || $sessionId === '') {
            throw new AuthenticationException('Authentication is required for this operation.');
        }

        /** @var mixed $id */
        $id = $user->getAttribute('id');

        return new AuthenticatedRequestData(
            user: new AuthenticatedUserData(
                id: is_string($id) ? $id : '',
                name: $user->name,
                email: $user->email,
            ),
            sessionId: $sessionId,
        );
    }
}
