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
        Schema::create('patient_consents', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('consent_type', 64);
            $table->string('granted_by_name', 160);
            $table->string('granted_by_relationship', 120)->nullable();
            $table->timestampTz('granted_at')->index();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampTz('revoked_at')->nullable()->index();
            $table->text('revocation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'patient_id', 'consent_type'], 'patient_consents_tenant_patient_type_index');
            $table->index(['tenant_id', 'patient_id', 'granted_at'], 'patient_consents_tenant_patient_granted_index');
        });

        PostgresSchema::createPartialIndex(
            'patient_consents',
            'patient_consents_active_lookup_partial_idx',
            ['tenant_id', 'patient_id', 'consent_type', 'granted_at'],
            '"revoked_at" IS NULL',
        );

        Schema::create('patient_insurance_policies', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('insurance_code', 64);
            $table->string('policy_number', 120);
            $table->string('member_number', 120)->nullable();
            $table->string('group_number', 120)->nullable();
            $table->string('plan_name', 160)->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['patient_id', 'insurance_code', 'policy_number'], 'patient_insurance_policy_unique');
            $table->index(['tenant_id', 'patient_id', 'effective_from'], 'patient_insurance_tenant_patient_effective_index');
        });

        DB::statement(
            'CREATE UNIQUE INDEX "patient_insurance_primary_unique" ON "patient_insurance_policies" ("patient_id") WHERE "is_primary" = true',
        );

        Schema::create('patient_external_references', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('integration_key', 64);
            $table->string('external_id', 191);
            $table->string('external_type', 64)->default('patient');
            $table->string('display_name', 160)->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('linked_at')->index();
            $table->timestampsTz();

            $table->unique(
                ['patient_id', 'integration_key', 'external_type', 'external_id'],
                'patient_external_refs_unique',
            );
            $table->index(
                ['tenant_id', 'patient_id', 'integration_key'],
                'patient_external_refs_tenant_patient_integration_index',
            );
        });
    }

    public function down(): void
    {
        PostgresSchema::dropIndex('patient_consents_active_lookup_partial_idx');
        PostgresSchema::dropIndex('patient_insurance_primary_unique');

        Schema::dropIfExists('patient_external_references');
        Schema::dropIfExists('patient_insurance_policies');
        Schema::dropIfExists('patient_consents');
    }
};
