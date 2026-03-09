<?php

use App\Shared\Infrastructure\Persistence\Schema\PostgresSchema;
use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_profiles', function (Blueprint $table): void {
            $table->foreignUuid('provider_id')->primary()->constrained('providers')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained('clinic_departments')->nullOnDelete();
            $table->foreignUuid('room_id')->nullable()->constrained('clinic_rooms')->nullOnDelete();
            $table->string('professional_title', 160)->nullable();
            $table->text('bio')->nullable();
            $table->unsignedSmallInteger('years_of_experience')->nullable();
            $table->boolean('is_accepting_new_patients')->default(true);
            $table->json('languages')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'department_id'], 'provider_profiles_tenant_department_index');
            $table->index(['tenant_id', 'room_id'], 'provider_profiles_tenant_room_index');
        });

        Schema::create('specialties', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'name'], 'specialties_tenant_name_index');
        });

        DB::statement(
            'CREATE UNIQUE INDEX "specialties_tenant_lower_name_unique" ON "specialties" ("tenant_id", LOWER("name"))',
        );

        Schema::create('provider_specialties', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->foreignUuid('specialty_id')->constrained('specialties')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestampTz('assigned_at')->index();
            $table->timestampsTz();

            $table->unique(['provider_id', 'specialty_id'], 'provider_specialties_provider_specialty_unique');
            $table->index(['tenant_id', 'provider_id'], 'provider_specialties_tenant_provider_index');
        });

        PostgresSchema::createPartialIndex(
            'provider_specialties',
            'provider_specialties_primary_unique',
            ['provider_id'],
            '"is_primary" = true',
        );

        Schema::create('provider_licenses', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->string('license_type', 64);
            $table->string('license_number', 120);
            $table->string('issuing_authority', 160);
            $table->string('jurisdiction', 120)->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['provider_id', 'license_type', 'license_number'],
                'provider_licenses_provider_type_number_unique',
            );
            $table->index(['tenant_id', 'provider_id', 'expires_on'], 'provider_licenses_tenant_provider_expiry_index');
        });

        Schema::create('provider_groups', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'name'], 'provider_groups_tenant_name_index');
            $table->index(['tenant_id', 'clinic_id'], 'provider_groups_tenant_clinic_index');
        });

        DB::statement(
            'CREATE UNIQUE INDEX "provider_groups_tenant_lower_name_unique" ON "provider_groups" ("tenant_id", LOWER("name"))',
        );

        Schema::create('provider_group_members', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('group_id')->constrained('provider_groups')->cascadeOnDelete();
            $table->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->timestampTz('joined_at')->index();
            $table->timestampsTz();

            $table->unique(['group_id', 'provider_id'], 'provider_group_members_group_provider_unique');
            $table->index(['tenant_id', 'group_id'], 'provider_group_members_tenant_group_index');
            $table->index(['tenant_id', 'provider_id'], 'provider_group_members_tenant_provider_index');
        });
    }

    public function down(): void
    {
        PostgresSchema::dropIndex('provider_groups_tenant_lower_name_unique');
        PostgresSchema::dropIndex('provider_specialties_primary_unique');
        PostgresSchema::dropIndex('specialties_tenant_lower_name_unique');

        Schema::dropIfExists('provider_group_members');
        Schema::dropIfExists('provider_groups');
        Schema::dropIfExists('provider_licenses');
        Schema::dropIfExists('provider_specialties');
        Schema::dropIfExists('specialties');
        Schema::dropIfExists('provider_profiles');
    }
};
