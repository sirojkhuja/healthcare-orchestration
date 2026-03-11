<?php

namespace App\Modules\Integrations\Presentation\Http\Controllers;

use App\Modules\Integrations\Application\Commands\HandleTelegramWebhookCommand;
use App\Modules\Integrations\Application\Handlers\HandleTelegramWebhookCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TelegramWebhookController
{
    public function process(Request $request, HandleTelegramWebhookCommandHandler $handler): JsonResponse
    {
        $rawPayload = $request->getContent();
        /** @var mixed $decoded */
        $decoded = json_decode($rawPayload, true);

        return response()->json($handler->handle(new HandleTelegramWebhookCommand(
            secretToken: $this->secretTokenHeader($request),
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

    private function secretTokenHeader(Request $request): string
    {
        $header = $request->header('X-Telegram-Bot-Api-Secret-Token');

        return is_string($header) ? trim($header) : '';
    }
}
