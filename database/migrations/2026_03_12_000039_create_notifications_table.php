<?php

use App\Shared\Infrastructure\Persistence\Schema\PostgresSchema;
use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('template_id')->constrained('notification_templates')->cascadeOnDelete();
            $table->string('template_code', 120);
            $table->unsignedInteger('template_version');
            $table->string('channel', 16);
            $table->json('recipient');
            $table->string('recipient_value', 255);
            $table->text('rendered_subject')->nullable();
            $table->text('rendered_body');
            $table->json('variables');
            $table->json('metadata');
            $table->string('status', 16);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(3);
            $table->string('provider_key', 64)->nullable();
            $table->string('provider_message_id', 191)->nullable();
            $table->string('last_error_code', 120)->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestampTz('queued_at');
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('canceled_at')->nullable();
            $table->text('canceled_reason')->nullable();
            $table->timestampTz('last_attempt_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status', 'channel'], 'notifications_tenant_status_channel_index');
            $table->index(['tenant_id', 'template_id', 'created_at'], 'notifications_tenant_template_created_index');
            $table->index(['tenant_id', 'created_at'], 'notifications_tenant_created_index');
        });

        PostgresSchema::createPartialIndex(
            'notifications',
            'notifications_tenant_queue_partial_idx',
            ['tenant_id', 'status', 'queued_at'],
            '"status" IN (\'queued\', \'failed\')',
        );
    }

    public function down(): void
    {
        PostgresSchema::dropIndex('notifications_tenant_queue_partial_idx');
        Schema::dropIfExists('notifications');
    }
};
