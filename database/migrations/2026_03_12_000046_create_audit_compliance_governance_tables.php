<?php

use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_retention_settings', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->unsignedInteger('retention_days');
            $table->timestampsTz();
        });

        Schema::create('pii_fields', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('object_type', 64);
            $table->string('field_path', 191);
            $table->string('classification', 32);
            $table->string('encryption_profile', 32);
            $table->unsignedInteger('key_version')->default(1);
            $table->string('status', 16)->default('active');
            $table->text('notes')->nullable();
            $table->timestampTz('last_rotated_at')->nullable();
            $table->timestampTz('last_reencrypted_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'object_type', 'field_path'], 'pii_fields_tenant_object_field_unique');
            $table->index(['tenant_id', 'status', 'object_type'], 'pii_fields_tenant_status_object_index');
            $table->index(['tenant_id', 'classification'], 'pii_fields_tenant_classification_index');
        });

        Schema::create('compliance_reports', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('status', 32);
            $table->unsignedInteger('requested_field_count');
            $table->unsignedInteger('processed_field_count');
            $table->unsignedInteger('skipped_field_count')->default(0);
            $table->json('field_ids');
            $table->json('summary');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'type', 'created_at'], 'compliance_reports_tenant_type_created_index');
            $table->index(['tenant_id', 'status', 'created_at'], 'compliance_reports_tenant_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_reports');
        Schema::dropIfExists('pii_fields');
        Schema::dropIfExists('audit_retention_settings');
    }
};
