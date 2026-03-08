<?php

namespace App\Modules\AuditCompliance\Infrastructure;

use App\Modules\AuditCompliance\Application\Contracts\AuditActorResolver;
use App\Modules\AuditCompliance\Application\Data\AuditActor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

final class AuthAuditActorResolver implements AuditActorResolver
{
    #[\Override]
    public function resolve(): AuditActor
    {
        $user = Auth::user();

        if (! $user instanceof Authenticatable) {
            /** @psalm-suppress MixedAssignment */
            $serviceName = config('app.name');

            return new AuditActor(
                type: 'service',
                id: null,
                name: is_string($serviceName) ? $serviceName : null,
            );
        }

        /** @psalm-suppress MixedAssignment */
        $actorId = $user->getAuthIdentifier();
        /** @psalm-suppress MixedAssignment */
        $actorName = method_exists($user, 'getAttribute') ? $user->getAttribute('name') : null;

        return new AuditActor(
            type: 'user',
            id: is_scalar($actorId) ? (string) $actorId : null,
            name: is_string($actorName) ? $actorName : null,
        );
    }
}
