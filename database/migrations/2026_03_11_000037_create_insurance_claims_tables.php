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
        Schema::create('insurance_payers', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 48);
            $table->string('name', 160);
            $table->string('insurance_code', 64);
            $table->string('contact_name', 160)->nullable();
            $table->string('contact_email', 190)->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'code'], 'insurance_payers_tenant_code_unique');
            $table->unique(['tenant_id', 'insurance_code'], 'insurance_payers_tenant_insurance_code_unique');
            $table->index(['tenant_id', 'is_active', 'name'], 'insurance_payers_tenant_active_name_index');
        });

        Schema::create('insurance_rules', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('payer_id')->constrained('insurance_payers')->cascadeOnDelete();
            $table->string('code', 48);
            $table->string('name', 160);
            $table->string('service_category', 120)->nullable();
            $table->boolean('requires_primary_policy')->default(false);
            $table->boolean('requires_attachment')->default(false);
            $table->decimal('max_claim_amount', 12, 2)->nullable();
            $table->unsignedInteger('submission_window_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'code'], 'insurance_rules_tenant_code_unique');
            $table->index(['tenant_id', 'payer_id', 'is_active'], 'insurance_rules_tenant_payer_active_index');
            $table->index(['tenant_id', 'service_category'], 'insurance_rules_tenant_service_category_index');
        });

        Schema::create('claim_number_sequences', function (Blueprint $table): void {
            $table->foreignUuid('tenant_id')->primary()->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('current_value')->default(0);
            $table->timestampsTz();
        });

        Schema::create('claims', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('payer_id')->constrained('insurance_payers');
            $table->foreignUuid('patient_id')->constrained('patients');
            $table->foreignUuid('invoice_id')->constrained('invoices');
            $table->foreignUuid('patient_policy_id')->nullable()->constrained('patient_insurance_policies')->nullOnDelete();
            $table->string('claim_number', 32);
            $table->string('payer_code', 48);
            $table->string('payer_name', 160);
            $table->string('payer_insurance_code', 64);
            $table->string('patient_display_name', 255);
            $table->string('invoice_number', 32);
            $table->string('patient_policy_number', 120)->nullable();
            $table->string('patient_member_number', 120)->nullable();
            $table->string('patient_group_number', 120)->nullable();
            $table->string('patient_plan_name', 160)->nullable();
            $table->string('currency', 3);
            $table->date('service_date');
            $table->decimal('billed_amount', 12, 2);
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 32)->index();
            $table->unsignedInteger('attachment_count')->default(0);
            $table->json('service_categories');
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('review_started_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('denied_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->text('denial_reason')->nullable();
            $table->json('last_transition')->nullable();
            $table->json('adjudication_history')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'claim_number'], 'claims_tenant_claim_number_unique');
            $table->index(['tenant_id', 'status', 'created_at'], 'claims_tenant_status_created_index');
            $table->index(['tenant_id', 'payer_id', 'service_date'], 'claims_tenant_payer_service_date_index');
            $table->index(['tenant_id', 'patient_id', 'service_date'], 'claims_tenant_patient_service_date_index');
            $table->index(['tenant_id', 'invoice_id'], 'claims_tenant_invoice_index');
        });

        Schema::create('claim_attachments', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('claim_id')->constrained('claims')->cascadeOnDelete();
            $table->string('attachment_type', 64)->nullable();
            $table->text('notes')->nullable();
            $table->string('file_name', 255);
            $table->string('mime_type', 160);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('disk', 32);
            $table->string('path', 500);
            $table->timestampTz('uploaded_at')->index();
            $table->timestampsTz();

            $table->index(['tenant_id', 'claim_id', 'uploaded_at'], 'claim_attachments_tenant_claim_uploaded_index');
        });

        PostgresSchema::createPartialIndex(
            'claims',
            'claims_tenant_active_status_partial_idx',
            ['tenant_id', 'status', 'service_date'],
            '"deleted_at" IS NULL',
        );
    }

    public function down(): void
    {
        PostgresSchema::dropIndex('claims_tenant_active_status_partial_idx');

        Schema::dropIfExists('claim_attachments');
        Schema::dropIfExists('claims');
        Schema::dropIfExists('claim_number_sequences');
        Schema::dropIfExists('insurance_rules');
        Schema::dropIfExists('insurance_payers');
    }
};
