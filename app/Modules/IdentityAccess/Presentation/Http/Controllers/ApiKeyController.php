<?php

namespace App\Modules\IdentityAccess\Presentation\Http\Controllers;

use App\Modules\IdentityAccess\Application\Commands\CreateApiKeyCommand;
use App\Modules\IdentityAccess\Application\Commands\RevokeApiKeyCommand;
use App\Modules\IdentityAccess\Application\Handlers\CreateApiKeyCommandHandler;
use App\Modules\IdentityAccess\Application\Handlers\ListApiKeysQueryHandler;
use App\Modules\IdentityAccess\Application\Handlers\RevokeApiKeyCommandHandler;
use App\Modules\IdentityAccess\Application\Queries\ListApiKeysQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApiKeyController
{
    public function create(Request $request, CreateApiKeyCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $result = $handler->handle(new CreateApiKeyCommand(
            name: $this->validatedString($validated, 'name'),
            expiresAt: $this->nullableValidatedString($validated, 'expires_at'),
        ));

        return response()->json([
            'status' => 'api_key_created',
        ] + $result->toArray(), 201);
    }

    public function list(ListApiKeysQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($apiKey) => $apiKey->toArray(),
                $handler->handle(new ListApiKeysQuery),
            ),
        ]);
    }

    public function revoke(string $keyId, RevokeApiKeyCommandHandler $handler): JsonResponse
    {
        $handler->handle(new RevokeApiKeyCommand($keyId));

        return response()->json([
            'status' => 'api_key_revoked',
        ]);
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

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function nullableValidatedString(array $validated, string $key): ?string
    {
        /** @var mixed $value */
        $value = $validated[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
