<?php

use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinics', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name', 160);
            $table->string('status', 32)->default('active')->index();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('city_code', 64)->nullable();
            $table->string('district_code', 64)->nullable();
            $table->string('address_line_1', 255)->nullable();
            $table->string('address_line_2', 255)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('activated_at')->nullable()->index();
            $table->timestampTz('deactivated_at')->nullable()->index();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'code'], 'clinics_tenant_code_unique');
            $table->index(['tenant_id', 'status', 'updated_at'], 'clinics_tenant_status_updated_index');
        });

        Schema::create('clinic_settings', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('clinic_id')->unique()->constrained('clinics')->cascadeOnDelete();
            $table->string('timezone', 64)->nullable();
            $table->unsignedSmallInteger('default_appointment_duration_minutes')->default(30);
            $table->unsignedSmallInteger('slot_interval_minutes')->default(15);
            $table->boolean('allow_walk_ins')->default(true);
            $table->boolean('require_appointment_confirmation')->default(false);
            $table->boolean('telemedicine_enabled')->default(false);
            $table->timestampsTz();

            $table->index(['tenant_id', 'clinic_id'], 'clinic_settings_tenant_clinic_index');
        });

        Schema::create('clinic_departments', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('phone_extension', 16)->nullable();
            $table->timestampsTz();

            $table->unique(['clinic_id', 'code'], 'clinic_departments_clinic_code_unique');
            $table->index(['tenant_id', 'clinic_id', 'name'], 'clinic_departments_tenant_clinic_name_index');
        });

        Schema::create('clinic_rooms', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained('clinic_departments')->nullOnDelete();
            $table->string('code', 32);
            $table->string('name', 160);
            $table->string('type', 32);
            $table->string('floor', 32)->nullable();
            $table->unsignedInteger('capacity')->default(1);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['clinic_id', 'code'], 'clinic_rooms_clinic_code_unique');
            $table->index(['tenant_id', 'clinic_id', 'type'], 'clinic_rooms_tenant_clinic_type_index');
        });

        Schema::create('clinic_work_hours', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('day_of_week', 16);
            $table->time('start_time');
            $table->time('end_time');
            $table->timestampsTz();

            $table->unique(['clinic_id', 'day_of_week', 'start_time', 'end_time'], 'clinic_work_hours_unique_interval');
            $table->index(['tenant_id', 'clinic_id', 'day_of_week'], 'clinic_work_hours_tenant_clinic_day_index');
        });

        Schema::create('clinic_holidays', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('name', 160);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_closed')->default(true);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'clinic_id', 'start_date', 'end_date'], 'clinic_holidays_tenant_clinic_dates_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_holidays');
        Schema::dropIfExists('clinic_work_hours');
        Schema::dropIfExists('clinic_rooms');
        Schema::dropIfExists('clinic_departments');
        Schema::dropIfExists('clinic_settings');
        Schema::dropIfExists('clinics');
    }
};
