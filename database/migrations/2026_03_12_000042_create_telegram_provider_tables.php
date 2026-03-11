<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_telegram_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->unique();
            $table->boolean('enabled')->default(false);
            $table->string('parse_mode', 32)->default('HTML');
            $table->json('broadcast_chat_ids');
            $table->json('support_chat_ids');
            $table->string('synced_bot_id', 64)->nullable();
            $table->string('synced_bot_username', 191)->nullable();
            $table->string('synced_webhook_url', 2048)->nullable();
            $table->unsignedInteger('synced_webhook_pending_update_count')->nullable();
            $table->timestampTz('synced_webhook_last_error_date')->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->timestampsTz();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
            $table->index(['tenant_id', 'enabled'], 'notification_telegram_settings_tenant_enabled_index');
        });

        Schema::create('telegram_webhook_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider_key', 64);
            $table->string('update_id', 64);
            $table->string('event_type', 64);
            $table->string('chat_id', 191)->nullable();
            $table->string('message_id', 64)->nullable();
            $table->uuid('resolved_tenant_id')->nullable();
            $table->string('payload_hash', 64);
            $table->string('secret_hash', 64);
            $table->string('outcome', 64);
            $table->string('error_code', 191)->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->json('payload');
            $table->json('response');
            $table->timestampsTz();

            $table->unique(['provider_key', 'update_id'], 'telegram_webhook_deliveries_provider_update_unique');
            $table->index(['resolved_tenant_id', 'processed_at'], 'telegram_webhook_deliveries_tenant_processed_index');
            $table->index(['chat_id', 'processed_at'], 'telegram_webhook_deliveries_chat_processed_index');
            $table->foreign('resolved_tenant_id')
                ->references('id')
                ->on('tenants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_webhook_deliveries');
        Schema::dropIfExists('notification_telegram_settings');
    }
};
