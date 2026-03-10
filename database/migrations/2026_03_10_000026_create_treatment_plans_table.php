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
        Schema::create('treatment_plans', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients');
            $table->foreignUuid('provider_id')->constrained('providers');
            $table->string('title', 255);
            $table->text('summary')->nullable();
            $table->text('goals')->nullable();
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->string('status', 32)->index();
            $table->json('last_transition')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('paused_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['tenant_id', 'patient_id', 'status'], 'treatment_plans_tenant_patient_status_index');
            $table->index(['tenant_id', 'provider_id', 'status'], 'treatment_plans_tenant_provider_status_index');
            $table->index(['tenant_id', 'planned_start_date'], 'treatment_plans_tenant_start_index');
        });

        PostgresSchema::createPartialIndex(
            'treatment_plans',
            'treatment_plans_tenant_active_created_partial_idx',
            ['tenant_id', 'created_at'],
            '"deleted_at" IS NULL',
        );
    }

    public function down(): void
    {
        PostgresSchema::dropIndex('treatment_plans_tenant_active_created_partial_idx');

        Schema::dropIfExists('treatment_plans');
    }
};
