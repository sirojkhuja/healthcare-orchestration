<?php

namespace App\Modules\Integrations\Presentation\Http\Controllers;

use App\Modules\Integrations\Application\Commands\HandleEImzoWebhookCommand;
use App\Modules\Integrations\Application\Handlers\HandleEImzoWebhookCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EImzoWebhookController
{
    public function process(Request $request, HandleEImzoWebhookCommandHandler $handler): JsonResponse
    {
        $rawPayload = $request->getContent();
        /** @var mixed $decoded */
        $decoded = json_decode($rawPayload, true);

        return response()->json($handler->handle(new HandleEImzoWebhookCommand(
            secret: $this->secretHeader($request),
            rawPayload: $rawPayload,
            payload: $this->payload($decoded),
        )));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            return [];
        }

        $normalized = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function secretHeader(Request $request): string
    {
        $header = $request->header('X-Integration-Webhook-Secret');

        return is_string($header) ? trim($header) : '';
    }
}
