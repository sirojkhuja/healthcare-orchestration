<?php

use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_email_settings', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('provider_key', 64)->default('email');
            $table->string('from_address', 191);
            $table->string('from_name', 191);
            $table->string('reply_to_address', 191)->nullable();
            $table->string('reply_to_name', 191)->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id'], 'notification_email_settings_tenant_unique');
            $table->index(['tenant_id', 'enabled'], 'notification_email_settings_tenant_enabled_index');
        });

        Schema::create('notification_email_events', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('notification_id')->nullable()->constrained('notifications')->nullOnDelete();
            $table->string('source', 32);
            $table->string('event_type', 32);
            $table->string('recipient_email', 191);
            $table->string('recipient_name', 191)->nullable();
            $table->text('subject');
            $table->string('provider_key', 64);
            $table->string('message_id', 191)->nullable();
            $table->string('error_code', 120)->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata');
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['tenant_id', 'created_at'], 'notification_email_events_tenant_created_index');
            $table->index(
                ['tenant_id', 'source', 'event_type', 'created_at'],
                'notification_email_events_tenant_source_event_created_index',
            );
            $table->index(
                ['tenant_id', 'notification_id', 'created_at'],
                'notification_email_events_tenant_notification_created_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_email_events');
        Schema::dropIfExists('notification_email_settings');
    }
};
