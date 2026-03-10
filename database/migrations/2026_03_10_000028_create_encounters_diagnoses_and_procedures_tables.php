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
        Schema::create('encounters', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients');
            $table->foreignUuid('provider_id')->constrained('providers');
            $table->foreignUuid('treatment_plan_id')->nullable()->constrained('treatment_plans')->nullOnDelete();
            $table->foreignUuid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignUuid('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignUuid('room_id')->nullable()->constrained('clinic_rooms')->nullOnDelete();
            $table->string('status', 32)->index();
            $table->timestampTz('encountered_at');
            $table->string('timezone', 64);
            $table->string('chief_complaint', 255)->nullable();
            $table->text('summary')->nullable();
            $table->text('notes')->nullable();
            $table->text('follow_up_instructions')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status', 'encountered_at'], 'encounters_tenant_status_time_index');
            $table->index(['tenant_id', 'patient_id', 'encountered_at'], 'encounters_tenant_patient_time_index');
            $table->index(['tenant_id', 'provider_id', 'encountered_at'], 'encounters_tenant_provider_time_index');
            $table->index(['tenant_id', 'treatment_plan_id', 'encountered_at'], 'encounters_tenant_plan_time_index');
            $table->index(['tenant_id', 'appointment_id', 'encountered_at'], 'encounters_tenant_appointment_time_index');
        });

        Schema::create('encounter_diagnoses', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->string('code', 32)->nullable();
            $table->string('display_name', 255);
            $table->string('diagnosis_type', 32)->index();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'encounter_id'], 'encounter_diagnoses_tenant_encounter_index');
        });

        Schema::create('encounter_procedures', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignUuid('treatment_item_id')->nullable()->constrained('treatment_plan_items')->nullOnDelete();
            $table->string('code', 32)->nullable();
            $table->string('display_name', 255);
            $table->timestampTz('performed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'encounter_id'], 'encounter_procedures_tenant_encounter_index');
            $table->index(['tenant_id', 'treatment_item_id'], 'encounter_procedures_tenant_item_index');
        });

        PostgresSchema::createPartialIndex(
            'encounters',
            'encounters_tenant_active_time_partial_idx',
            ['tenant_id', 'encountered_at'],
            '"deleted_at" IS NULL',
        );
    }

    public function down(): void
    {
        PostgresSchema::dropIndex('encounters_tenant_active_time_partial_idx');

        Schema::dropIfExists('encounter_procedures');
        Schema::dropIfExists('encounter_diagnoses');
        Schema::dropIfExists('encounters');
    }
};
