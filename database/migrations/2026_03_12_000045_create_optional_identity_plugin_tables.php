<?php

use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('myid_verifications', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('webhook_id')->constrained('integration_webhooks')->cascadeOnDelete();
            $table->string('external_reference', 191);
            $table->string('provider_reference', 191);
            $table->string('status', 32);
            $table->json('subject');
            $table->json('metadata');
            $table->json('result_payload')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'provider_reference'], 'myid_verifications_tenant_provider_reference_unique');
            $table->index(['tenant_id', 'status', 'created_at'], 'myid_verifications_tenant_status_created_index');
            $table->index(['tenant_id', 'external_reference'], 'myid_verifications_tenant_external_reference_index');
        });

        Schema::create('eimzo_sign_requests', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('webhook_id')->constrained('integration_webhooks')->cascadeOnDelete();
            $table->string('external_reference', 191);
            $table->string('provider_reference', 191);
            $table->string('status', 32);
            $table->string('document_hash', 191);
            $table->string('document_name', 255);
            $table->json('signer')->nullable();
            $table->json('metadata');
            $table->json('signature_payload')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'provider_reference'], 'eimzo_sign_requests_tenant_provider_reference_unique');
            $table->index(['tenant_id', 'status', 'created_at'], 'eimzo_sign_requests_tenant_status_created_index');
            $table->index(['tenant_id', 'external_reference'], 'eimzo_sign_requests_tenant_external_reference_index');
        });

        Schema::create('integration_plugin_webhook_deliveries', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->string('integration_key', 64);
            $table->foreignUuid('webhook_id')->constrained('integration_webhooks')->cascadeOnDelete();
            $table->foreignUuid('resolved_tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('delivery_id', 191);
            $table->string('provider_reference', 191)->nullable();
            $table->string('event_type', 64);
            $table->string('payload_hash', 64);
            $table->string('secret_hash', 64);
            $table->string('outcome', 32);
            $table->string('error_code', 120)->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->timestampsTz();

            $table->unique(['integration_key', 'webhook_id', 'delivery_id'], 'integration_plugin_webhooks_integration_webhook_delivery_unique');
            $table->index(['resolved_tenant_id', 'processed_at'], 'integration_plugin_webhooks_tenant_processed_index');
            $table->index(['integration_key', 'provider_reference'], 'integration_plugin_webhooks_integration_reference_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_plugin_webhook_deliveries');
        Schema::dropIfExists('eimzo_sign_requests');
        Schema::dropIfExists('myid_verifications');
    }
};
