<?php

namespace Tests\Fixtures\Shared\Idempotency;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FakeIdempotentCommandAction
{
    public int $invocationCount = 0;

    public function __invoke(Request $request): JsonResponse
    {
        $this->invocationCount++;

        return response()->json([
            'sequence' => $this->invocationCount,
            'tenant_id' => $request->attributes->get('tenant_id'),
            'payload' => $request->json()->all(),
        ], 201);
    }
}
