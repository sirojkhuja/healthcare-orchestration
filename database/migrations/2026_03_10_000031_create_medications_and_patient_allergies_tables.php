<?php

use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medications', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 120);
            $table->string('name', 255);
            $table->string('generic_name', 255)->nullable();
            $table->string('form', 64)->nullable();
            $table->string('strength', 120)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'code'], 'medications_tenant_code_unique');
            $table->index(['tenant_id', 'is_active', 'name'], 'medications_tenant_active_name_index');
            $table->index(['tenant_id', 'name'], 'medications_tenant_name_index');
        });

        Schema::create('patient_allergies', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('medication_id')->nullable()->constrained('medications')->nullOnDelete();
            $table->string('allergen_name', 255);
            $table->text('reaction')->nullable();
            $table->string('severity', 32)->nullable();
            $table->timestampTz('noted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'patient_id'], 'patient_allergies_tenant_patient_index');
            $table->index(['tenant_id', 'patient_id', 'allergen_name'], 'patient_allergies_tenant_patient_allergen_index');
            $table->index(['tenant_id', 'patient_id', 'severity'], 'patient_allergies_tenant_patient_severity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_allergies');
        Schema::dropIfExists('medications');
    }
};
