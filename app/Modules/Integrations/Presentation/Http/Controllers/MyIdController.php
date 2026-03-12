<?php

namespace App\Modules\Integrations\Presentation\Http\Controllers;

use App\Modules\Integrations\Application\Commands\VerifyMyIdCommand;
use App\Modules\Integrations\Application\Handlers\VerifyMyIdCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MyIdController
{
    public function verify(Request $request, VerifyMyIdCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'external_reference' => ['required', 'string', 'max:191'],
            'subject' => ['required', 'array'],
            'metadata' => ['sometimes', 'array'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'status' => 'myid_verification_created',
            'data' => $handler->handle(new VerifyMyIdCommand($validated))->toArray(),
        ], 201);
    }
}
