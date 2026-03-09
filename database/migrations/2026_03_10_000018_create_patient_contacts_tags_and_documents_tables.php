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
        Schema::create('patient_contacts', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('relationship', 120)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_emergency')->default(false);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'patient_id', 'created_at'], 'patient_contacts_tenant_patient_created_index');
            $table->index(['tenant_id', 'patient_id', 'is_emergency'], 'patient_contacts_tenant_patient_emergency_index');
        });

        DB::statement(
            'CREATE UNIQUE INDEX "patient_contacts_patient_primary_unique" ON "patient_contacts" ("patient_id") WHERE "is_primary" = true',
        );

        Schema::create('patient_tags', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('tag', 120);
            $table->timestampsTz();

            $table->unique(['patient_id', 'tag'], 'patient_tags_patient_tag_unique');
            $table->index(['tenant_id', 'patient_id', 'tag'], 'patient_tags_tenant_patient_tag_index');
        });

        Schema::create('patient_documents', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('title', 255);
            $table->string('document_type', 64)->nullable();
            $table->string('storage_disk', 64);
            $table->string('storage_path');
            $table->string('file_name', 255);
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->timestampTz('uploaded_at')->index();
            $table->timestampsTz();

            $table->index(['tenant_id', 'patient_id', 'uploaded_at'], 'patient_documents_tenant_patient_uploaded_index');
        });

        PostgresSchema::createPartialIndex(
            'patient_documents',
            'patient_documents_tenant_patient_recent_partial_idx',
            ['tenant_id', 'patient_id', 'uploaded_at'],
            '"uploaded_at" IS NOT NULL',
        );
    }

    public function down(): void
    {
        PostgresSchema::dropIndex('patient_documents_tenant_patient_recent_partial_idx');
        PostgresSchema::dropIndex('patient_contacts_patient_primary_unique');

        Schema::dropIfExists('patient_documents');
        Schema::dropIfExists('patient_tags');
        Schema::dropIfExists('patient_contacts');
    }
};
