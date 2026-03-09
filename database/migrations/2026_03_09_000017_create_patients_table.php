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
        Schema::create('patients', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('middle_name', 120)->nullable();
            $table->string('preferred_name', 120)->nullable();
            $table->string('sex', 32)->index();
            $table->date('birth_date');
            $table->string('national_id', 64)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('city_code', 64)->nullable();
            $table->string('district_code', 64)->nullable();
            $table->string('address_line_1', 255)->nullable();
            $table->string('address_line_2', 255)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'sex', 'birth_date'], 'patients_tenant_sex_birth_index');
        });

        PostgresSchema::createPartialIndex(
            'patients',
            'patients_tenant_active_directory_partial_idx',
            ['tenant_id', 'last_name', 'first_name'],
            '"deleted_at" IS NULL',
        );
        PostgresSchema::createPartialIndex(
            'patients',
            'patients_tenant_active_updated_partial_idx',
            ['tenant_id', 'updated_at'],
            '"deleted_at" IS NULL',
        );
        DB::statement(
            'CREATE UNIQUE INDEX "patients_tenant_national_id_active_unique" ON "patients" ("tenant_id", "national_id") WHERE "national_id" IS NOT NULL AND "deleted_at" IS NULL',
        );
    }

    public function down(): void
    {
        PostgresSchema::dropIndex('patients_tenant_national_id_active_unique');
        PostgresSchema::dropIndex('patients_tenant_active_updated_partial_idx');
        PostgresSchema::dropIndex('patients_tenant_active_directory_partial_idx');

        Schema::dropIfExists('patients');
    }
};
