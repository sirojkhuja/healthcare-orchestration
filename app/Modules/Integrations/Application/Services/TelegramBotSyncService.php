<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Notifications\Application\Contracts\TelegramBotGateway;
use App\Modules\Notifications\Application\Contracts\TelegramProviderSettingsRepository;
use App\Modules\Notifications\Application\Data\TelegramSyncResultData;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class TelegramBotSyncService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TelegramProviderSettingsRepository $telegramProviderSettingsRepository,
        private readonly TelegramBotGateway $telegramBotGateway,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function sync(): TelegramSyncResultData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $settings = $this->telegramProviderSettingsRepository->get($tenantId);

        if (! $settings->enabled) {
            throw new ConflictHttpException('Telegram bot sync requires the Telegram provider to be enabled.');
        }

        $secretToken = trim(config()->string('notifications.telegram.webhook_secret', ''));

        if ($secretToken === '') {
            throw new ConflictHttpException('Telegram bot sync requires TELEGRAM_WEBHOOK_SECRET to be configured.');
        }

        $expectedWebhookUrl = $this->expectedWebhookUrl();
        $bot = $this->telegramBotGateway->getMe();
        $webhook = $this->telegramBotGateway->getWebhookInfo();

        if ($webhook->url !== $expectedWebhookUrl) {
            $webhook = $this->telegramBotGateway->setWebhook($expectedWebhookUrl, $secretToken);
        }

        $updated = $this->telegramProviderSettingsRepository->save($tenantId, [
            'synced_bot_id' => $bot->botId,
            'synced_bot_username' => $bot->username,
            'synced_webhook_url' => $webhook->url,
            'synced_webhook_pending_update_count' => $webhook->pendingUpdateCount,
            'synced_webhook_last_error_date' => $webhook->lastErrorDate,
            'last_synced_at' => CarbonImmutable::now(),
        ]);
        $result = new TelegramSyncResultData(
            settings: $updated,
            bot: $bot,
            webhook: $webhook,
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'telegram.bot_synced',
            objectType: 'telegram_provider',
            objectId: $tenantId,
            after: $result->toArray(),
        ));

        return $result;
    }

    private function expectedWebhookUrl(): string
    {
        $appUrl = rtrim(config()->string('app.url', ''), '/');

        if ($appUrl === '') {
            throw new ConflictHttpException('Telegram bot sync requires APP_URL to be configured.');
        }

        $route = Route::getRoutes()->getByName('webhooks.telegram.process');

        if ($route === null) {
            throw new ConflictHttpException('Telegram bot sync could not resolve the Telegram webhook route.');
        }

        return $appUrl.'/'.ltrim($route->uri(), '/');
    }
}
