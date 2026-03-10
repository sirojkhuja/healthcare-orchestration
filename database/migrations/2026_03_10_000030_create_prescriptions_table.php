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
        Schema::create('prescriptions', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients');
            $table->foreignUuid('provider_id')->constrained('providers');
            $table->foreignUuid('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->foreignUuid('treatment_item_id')->nullable()->constrained('treatment_plan_items')->nullOnDelete();
            $table->string('medication_name', 255);
            $table->string('medication_code', 120)->nullable();
            $table->string('dosage', 255);
            $table->string('route', 120);
            $table->string('frequency', 120);
            $table->decimal('quantity', 12, 2);
            $table->string('quantity_unit', 64)->nullable();
            $table->unsignedSmallInteger('authorized_refills')->default(0);
            $table->text('instructions')->nullable();
            $table->text('notes')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 32)->index();
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('dispensed_at')->nullable();
            $table->timestampTz('canceled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->json('last_transition')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status', 'issued_at'], 'prescriptions_tenant_status_issued_index');
            $table->index(['tenant_id', 'patient_id', 'issued_at'], 'prescriptions_tenant_patient_issued_index');
            $table->index(['tenant_id', 'provider_id', 'issued_at'], 'prescriptions_tenant_provider_issued_index');
            $table->index(['tenant_id', 'encounter_id', 'issued_at'], 'prescriptions_tenant_encounter_issued_index');
            $table->index(['tenant_id', 'starts_on'], 'prescriptions_tenant_starts_on_index');
        });

        PostgresSchema::createPartialIndex(
            'prescriptions',
            'prescriptions_tenant_active_effective_partial_idx',
            ['tenant_id', 'created_at'],
            '"deleted_at" IS NULL',
        );
    }

    public function down(): void
    {
        PostgresSchema::dropIndex('prescriptions_tenant_active_effective_partial_idx');

        Schema::dropIfExists('prescriptions');
    }
};
