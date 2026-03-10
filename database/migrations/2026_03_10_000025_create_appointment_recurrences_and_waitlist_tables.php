<?php

use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_recurrences', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('source_appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients');
            $table->foreignUuid('provider_id')->constrained('providers');
            $table->foreignUuid('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->uuid('room_id')->nullable();
            $table->string('frequency', 32);
            $table->unsignedSmallInteger('interval');
            $table->unsignedSmallInteger('occurrence_count')->nullable();
            $table->date('until_date')->nullable();
            $table->string('timezone', 64);
            $table->string('status', 32);
            $table->text('canceled_reason')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status'], 'appointment_recurrences_tenant_status_index');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->foreignUuid('recurrence_id')->nullable()->after('room_id')->constrained('appointment_recurrences')->nullOnDelete();
            $table->index(['tenant_id', 'recurrence_id'], 'appointments_tenant_recurrence_index');
        });

        Schema::create('appointment_waitlist_entries', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients');
            $table->foreignUuid('provider_id')->constrained('providers');
            $table->foreignUuid('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->uuid('room_id')->nullable();
            $table->date('desired_date_from');
            $table->date('desired_date_to');
            $table->string('preferred_start_time', 5)->nullable();
            $table->string('preferred_end_time', 5)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 32);
            $table->foreignUuid('booked_appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->json('offered_slot')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status'], 'appointment_waitlist_tenant_status_index');
            $table->index(['tenant_id', 'provider_id', 'desired_date_from'], 'appointment_waitlist_provider_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_waitlist_entries');

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex('appointments_tenant_recurrence_index');
            $table->dropConstrainedForeignId('recurrence_id');
        });

        Schema::dropIfExists('appointment_recurrences');
    }
};
