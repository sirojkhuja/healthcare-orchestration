<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('code', 120);
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('source', 64);
            $table->string('format', 16);
            $table->jsonb('filters');
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'source', 'created_at'], 'reports_tenant_source_created_idx');
        });

        DB::statement(
            'CREATE UNIQUE INDEX reports_tenant_code_active_unique ON reports (tenant_id, code) WHERE deleted_at IS NULL',
        );

        Schema::create('report_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('report_id')->index();
            $table->uuid('tenant_id')->index();
            $table->string('status', 32);
            $table->string('format', 16);
            $table->unsignedInteger('row_count');
            $table->string('file_name', 255);
            $table->string('storage_disk', 64);
            $table->string('storage_path', 1024);
            $table->timestampTz('generated_at');
            $table->timestampsTz();

            $table->foreign('report_id')->references('id')->on('reports')->cascadeOnDelete();
            $table->index(['report_id', 'generated_at'], 'report_runs_report_generated_idx');
        });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS reports_tenant_code_active_unique');
        Schema::dropIfExists('report_runs');
        Schema::dropIfExists('reports');
    }
};
