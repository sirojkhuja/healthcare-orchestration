<?php

namespace App\Modules\IdentityAccess\Presentation\Http\Controllers;

use App\Modules\IdentityAccess\Application\Commands\LoginCommand;
use App\Modules\IdentityAccess\Application\Commands\LogoutCommand;
use App\Modules\IdentityAccess\Application\Commands\RefreshTokenCommand;
use App\Modules\IdentityAccess\Application\Handlers\GetMeQueryHandler;
use App\Modules\IdentityAccess\Application\Handlers\LoginCommandHandler;
use App\Modules\IdentityAccess\Application\Handlers\LogoutCommandHandler;
use App\Modules\IdentityAccess\Application\Handlers\RefreshTokenCommandHandler;
use App\Modules\IdentityAccess\Application\Queries\GetMeQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController
{
    public function login(Request $request, LoginCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $result = $handler->handle(new LoginCommand(
            email: $this->validatedString($validated, 'email'),
            password: $this->validatedString($validated, 'password'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        ));

        return response()->json($result->toArray());
    }

    public function logout(Request $request, LogoutCommandHandler $handler): JsonResponse
    {
        $handler->handle(new LogoutCommand(
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        ));

        return response()->json([
            'status' => 'logged_out',
        ]);
    }

    public function me(GetMeQueryHandler $handler): JsonResponse
    {
        return response()->json($handler->handle(new GetMeQuery)->toArray());
    }

    public function refresh(Request $request, RefreshTokenCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'refresh_token' => ['required', 'string', 'min:32'],
        ]);

        $result = $handler->handle(new RefreshTokenCommand(
            refreshToken: $this->validatedString($validated, 'refresh_token'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        ));

        return response()->json($result->toArray());
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function validatedString(array $validated, string $key): string
    {
        /** @var mixed $value */
        $value = $validated[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}
