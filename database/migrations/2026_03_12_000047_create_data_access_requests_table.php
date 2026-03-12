<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_access_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('request_type', 64);
            $table->string('status', 32)->index();
            $table->string('requested_by_name', 160);
            $table->string('requested_by_relationship', 120)->nullable();
            $table->timestampTz('requested_at')->index();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->uuid('approved_by_user_id')->nullable();
            $table->string('approved_by_name', 160)->nullable();
            $table->timestampTz('denied_at')->nullable();
            $table->uuid('denied_by_user_id')->nullable();
            $table->string('denied_by_name', 160)->nullable();
            $table->text('denial_reason')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'requested_at'], 'dar_tenant_requested_index');
            $table->index(['tenant_id', 'status', 'requested_at'], 'dar_tenant_status_requested_index');
            $table->index(['tenant_id', 'patient_id', 'requested_at'], 'dar_tenant_patient_requested_index');
            $table->index(['tenant_id', 'request_type', 'requested_at'], 'dar_tenant_type_requested_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_access_requests');
    }
};
