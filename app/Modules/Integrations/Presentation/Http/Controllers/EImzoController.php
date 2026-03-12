<?php

namespace App\Modules\Integrations\Presentation\Http\Controllers;

use App\Modules\Integrations\Application\Commands\CreateEImzoSignRequestCommand;
use App\Modules\Integrations\Application\Handlers\CreateEImzoSignRequestCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EImzoController
{
    public function sign(Request $request, CreateEImzoSignRequestCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'external_reference' => ['required', 'string', 'max:191'],
            'document_hash' => ['required', 'string', 'max:191'],
            'document_name' => ['required', 'string', 'max:255'],
            'signer' => ['sometimes', 'array'],
            'metadata' => ['sometimes', 'array'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'status' => 'eimzo_sign_request_created',
            'data' => $handler->handle(new CreateEImzoSignRequestCommand($validated))->toArray(),
        ], 201);
    }
}
