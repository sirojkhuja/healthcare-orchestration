<?php

namespace App\Modules\Notifications\Presentation\Http\Controllers;

use App\Modules\Notifications\Application\Commands\SendTestEmailCommand;
use App\Modules\Notifications\Application\Commands\SendTestSmsCommand;
use App\Modules\Notifications\Application\Commands\SendTestTelegramCommand;
use App\Modules\Notifications\Application\Handlers\SendTestEmailCommandHandler;
use App\Modules\Notifications\Application\Handlers\SendTestSmsCommandHandler;
use App\Modules\Notifications\Application\Handlers\SendTestTelegramCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationChannelTestController
{
    public function sendEmail(Request $request, SendTestEmailCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'recipient' => ['required', 'array'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);
        /** @var array<string, mixed> $validated */
        $data = $handler->handle(new SendTestEmailCommand($validated));

        return response()->json([
            'status' => $this->isSent($data['result'] ?? null) ? 'notification_test_email_sent' : 'notification_test_email_failed',
            'data' => $data,
        ]);
    }

    public function sendSms(Request $request, SendTestSmsCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'recipient' => ['required', 'array'],
            'message' => ['required', 'string', 'max:1000'],
            'message_type' => ['sometimes', 'nullable', 'string'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);
        /** @var array<string, mixed> $validated */
        $data = $handler->handle(new SendTestSmsCommand($validated));

        return response()->json([
            'status' => $this->isSent($data['result'] ?? null) ? 'notification_test_sms_sent' : 'notification_test_sms_failed',
            'data' => $data,
        ]);
    }

    public function sendTelegram(Request $request, SendTestTelegramCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'recipient' => ['required', 'array'],
            'message' => ['required', 'string', 'max:4000'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);
        /** @var array<string, mixed> $validated */
        $data = $handler->handle(new SendTestTelegramCommand($validated));

        return response()->json([
            'status' => $this->isSent($data['result'] ?? null) ? 'notification_test_telegram_sent' : 'notification_test_telegram_failed',
            'data' => $data,
        ]);
    }

    private function isSent(mixed $result): bool
    {
        return is_array($result) && (($result['status'] ?? null) === 'sent');
    }
}
