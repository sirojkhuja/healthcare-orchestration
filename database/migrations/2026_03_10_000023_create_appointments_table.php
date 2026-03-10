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
        Schema::create('appointments', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients');
            $table->foreignUuid('provider_id')->constrained('providers');
            $table->foreignUuid('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignUuid('room_id')->nullable()->constrained('clinic_rooms')->nullOnDelete();
            $table->string('status', 32)->index();
            $table->timestampTz('scheduled_start_at');
            $table->timestampTz('scheduled_end_at');
            $table->string('timezone', 64);
            $table->json('last_transition')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status', 'scheduled_start_at'], 'appointments_tenant_status_start_index');
            $table->index(['tenant_id', 'patient_id', 'scheduled_start_at'], 'appointments_tenant_patient_start_index');
            $table->index(['tenant_id', 'provider_id', 'scheduled_start_at'], 'appointments_tenant_provider_start_index');
            $table->index(['tenant_id', 'clinic_id', 'scheduled_start_at'], 'appointments_tenant_clinic_start_index');
            $table->index(['tenant_id', 'room_id', 'scheduled_start_at'], 'appointments_tenant_room_start_index');
        });

        PostgresSchema::createPartialIndex(
            'appointments',
            'appointments_tenant_active_start_partial_idx',
            ['tenant_id', 'scheduled_start_at'],
            '"deleted_at" IS NULL',
        );
    }

    public function down(): void
    {
        PostgresSchema::dropIndex('appointments_tenant_active_start_partial_idx');

        Schema::dropIfExists('appointments');
    }
};
