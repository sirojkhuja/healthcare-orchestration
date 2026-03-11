<?php

namespace App\Modules\Integrations\Presentation\Http\Controllers;

use App\Modules\Integrations\Application\Commands\SendEskizSmsCommand;
use App\Modules\Integrations\Application\Commands\SendPlayMobileSmsCommand;
use App\Modules\Integrations\Application\Commands\SendTextUpSmsCommand;
use App\Modules\Integrations\Application\Handlers\SendEskizSmsCommandHandler;
use App\Modules\Integrations\Application\Handlers\SendPlayMobileSmsCommandHandler;
use App\Modules\Integrations\Application\Handlers\SendTextUpSmsCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SmsProviderSendController
{
    public function sendEskiz(Request $request, SendEskizSmsCommandHandler $handler): JsonResponse
    {
        return $this->response($handler->handle(new SendEskizSmsCommand($this->payload($request))));
    }

    public function sendPlayMobile(Request $request, SendPlayMobileSmsCommandHandler $handler): JsonResponse
    {
        return $this->response($handler->handle(new SendPlayMobileSmsCommand($this->payload($request))));
    }

    public function sendTextUp(Request $request, SendTextUpSmsCommandHandler $handler): JsonResponse
    {
        return $this->response($handler->handle(new SendTextUpSmsCommand($this->payload($request))));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $validated = $request->validate([
            'recipient' => ['required', 'array'],
            'message' => ['required', 'string', 'max:1000'],
            'message_type' => ['sometimes', 'nullable', 'string'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        /** @var array<string, mixed> $validated */
        return $validated;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function response(array $data): JsonResponse
    {
        return response()->json([
            'status' => $this->isSent($data['result'] ?? null) ? 'sms_sent' : 'sms_failed',
            'data' => $data,
        ]);
    }

    private function isSent(mixed $result): bool
    {
        return is_array($result) && (($result['status'] ?? null) === 'sent');
    }
}
