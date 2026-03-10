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
        Schema::create('lab_tests', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 120);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('specimen_type', 64);
            $table->string('result_type', 16);
            $table->string('unit', 64)->nullable();
            $table->string('reference_range', 255)->nullable();
            $table->string('lab_provider_key', 120)->index();
            $table->string('external_test_code', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'lab_provider_key', 'code'], 'lab_tests_tenant_provider_code_unique');
            $table->index(['tenant_id', 'is_active', 'name'], 'lab_tests_tenant_active_name_index');
        });

        Schema::create('lab_orders', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients');
            $table->foreignUuid('provider_id')->constrained('providers');
            $table->foreignUuid('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->foreignUuid('treatment_item_id')->nullable()->constrained('treatment_plan_items')->nullOnDelete();
            $table->foreignUuid('lab_test_id')->nullable()->constrained('lab_tests')->nullOnDelete();
            $table->string('lab_provider_key', 120)->index();
            $table->string('requested_test_code', 120);
            $table->string('requested_test_name', 255);
            $table->string('requested_specimen_type', 64);
            $table->string('requested_result_type', 16);
            $table->string('status', 32)->index();
            $table->timestampTz('ordered_at');
            $table->string('timezone', 64);
            $table->text('notes')->nullable();
            $table->string('external_order_id', 191)->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('specimen_collected_at')->nullable();
            $table->timestampTz('specimen_received_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('canceled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->json('last_transition')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->unique(['lab_provider_key', 'external_order_id'], 'lab_orders_provider_external_order_unique');
            $table->index(['tenant_id', 'status', 'ordered_at'], 'lab_orders_tenant_status_ordered_index');
            $table->index(['tenant_id', 'patient_id', 'ordered_at'], 'lab_orders_tenant_patient_ordered_index');
            $table->index(['tenant_id', 'provider_id', 'ordered_at'], 'lab_orders_tenant_provider_ordered_index');
            $table->index(['tenant_id', 'encounter_id', 'ordered_at'], 'lab_orders_tenant_encounter_ordered_index');
            $table->index(['tenant_id', 'lab_test_id', 'ordered_at'], 'lab_orders_tenant_test_ordered_index');
        });

        Schema::create('lab_results', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('lab_order_id')->constrained('lab_orders')->cascadeOnDelete();
            $table->foreignUuid('lab_test_id')->nullable()->constrained('lab_tests')->nullOnDelete();
            $table->string('external_result_id', 191)->nullable();
            $table->string('status', 32)->index();
            $table->timestampTz('observed_at');
            $table->timestampTz('received_at');
            $table->string('value_type', 16);
            $table->decimal('value_numeric', 14, 4)->nullable();
            $table->text('value_text')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->json('value_json')->nullable();
            $table->string('unit', 64)->nullable();
            $table->string('reference_range', 255)->nullable();
            $table->string('abnormal_flag', 32)->nullable();
            $table->text('notes')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestampsTz();

            $table->unique(['lab_order_id', 'external_result_id'], 'lab_results_order_external_result_unique');
            $table->index(['tenant_id', 'lab_order_id', 'observed_at'], 'lab_results_tenant_order_observed_index');
        });

        Schema::create('lab_webhook_deliveries', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->string('provider_key', 120)->index();
            $table->string('delivery_id', 191);
            $table->string('payload_hash', 64);
            $table->string('signature_hash', 64);
            $table->foreignUuid('lab_order_id')->nullable()->constrained('lab_orders')->nullOnDelete();
            $table->foreignUuid('resolved_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('outcome', 32)->index();
            $table->timestampTz('occurred_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestampsTz();

            $table->unique(['provider_key', 'delivery_id'], 'lab_webhook_deliveries_provider_delivery_unique');
            $table->index(['resolved_tenant_id', 'processed_at'], 'lab_webhook_deliveries_tenant_processed_index');
        });

        PostgresSchema::createPartialIndex(
            'lab_orders',
            'lab_orders_tenant_active_ordered_partial_idx',
            ['tenant_id', 'ordered_at'],
            '"deleted_at" IS NULL',
        );
    }

    public function down(): void
    {
        PostgresSchema::dropIndex('lab_orders_tenant_active_ordered_partial_idx');

        Schema::dropIfExists('lab_webhook_deliveries');
        Schema::dropIfExists('lab_results');
        Schema::dropIfExists('lab_orders');
        Schema::dropIfExists('lab_tests');
    }
};
