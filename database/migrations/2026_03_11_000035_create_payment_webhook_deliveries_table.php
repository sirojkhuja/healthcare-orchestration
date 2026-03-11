<?php

use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_deliveries', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->string('provider_key', 120)->index();
            $table->string('method', 80)->index();
            $table->string('replay_key', 191)->nullable();
            $table->string('provider_transaction_id', 191)->nullable()->index();
            $table->string('request_id', 191)->nullable();
            $table->foreignUuid('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignUuid('resolved_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('payload_hash', 64);
            $table->string('auth_hash', 64);
            $table->bigInteger('provider_time_millis')->nullable()->index();
            $table->string('outcome', 32)->index();
            $table->string('provider_error_code', 64)->nullable();
            $table->text('provider_error_message')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->timestampsTz();

            $table->unique(['provider_key', 'method', 'replay_key'], 'payment_webhook_deliveries_provider_method_replay_unique');
            $table->index(['provider_key', 'method', 'provider_time_millis'], 'payment_webhook_deliveries_provider_method_time_index');
            $table->index(['resolved_tenant_id', 'processed_at'], 'payment_webhook_deliveries_tenant_processed_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_deliveries');
    }
};
