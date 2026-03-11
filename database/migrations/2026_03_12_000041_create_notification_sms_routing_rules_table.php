<?php

use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_sms_routing_rules', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('message_type', 32);
            $table->json('providers');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'message_type'], 'notification_sms_routing_rules_tenant_message_unique');
            $table->index(['tenant_id', 'message_type'], 'notification_sms_routing_rules_tenant_message_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_sms_routing_rules');
    }
};
