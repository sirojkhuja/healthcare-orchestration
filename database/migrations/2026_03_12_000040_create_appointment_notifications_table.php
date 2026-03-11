<?php

use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_notifications', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignUuid('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->string('notification_type', 24);
            $table->string('channel', 16);
            $table->foreignUuid('template_id')->constrained('notification_templates')->cascadeOnDelete();
            $table->string('template_code', 120);
            $table->string('recipient_value', 255);
            $table->string('window_key', 32)->nullable();
            $table->timestampTz('requested_at');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'notification_id'], 'appointment_notifications_tenant_notification_unique');
            $table->index(
                ['tenant_id', 'appointment_id', 'notification_type', 'channel', 'window_key'],
                'appointment_notifications_dispatch_lookup_index',
            );
            $table->index(
                ['tenant_id', 'appointment_id', 'created_at'],
                'appointment_notifications_tenant_appointment_created_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_notifications');
    }
};
