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
        Schema::create('notification_templates', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 120);
            $table->string('name', 255);
            $table->string('channel', 16);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('current_version')->default(1);
            $table->text('subject_template')->nullable();
            $table->text('body_template');
            $table->json('placeholders');
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['tenant_id', 'channel', 'is_active'], 'notification_templates_tenant_channel_active_index');
            $table->index(['tenant_id', 'updated_at'], 'notification_templates_tenant_updated_index');
        });

        Schema::create('notification_template_versions', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('template_id')->constrained('notification_templates')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('code', 120);
            $table->string('name', 255);
            $table->string('channel', 16);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('subject_template')->nullable();
            $table->text('body_template');
            $table->json('placeholders');
            $table->timestampsTz();

            $table->unique(['template_id', 'version'], 'notification_template_versions_template_version_unique');
            $table->index(['tenant_id', 'template_id', 'version'], 'notification_template_versions_lookup_index');
        });

        PostgresSchema::createPartialIndex(
            'notification_templates',
            'notification_templates_active_listing_partial_idx',
            ['tenant_id', 'channel', 'updated_at'],
            '"deleted_at" IS NULL',
        );
        DB::statement(
            'CREATE UNIQUE INDEX "notification_templates_tenant_code_active_unique" ON "notification_templates" ("tenant_id", "code") WHERE "deleted_at" IS NULL',
        );
    }

    public function down(): void
    {
        PostgresSchema::dropIndex('notification_templates_tenant_code_active_unique');
        PostgresSchema::dropIndex('notification_templates_active_listing_partial_idx');

        Schema::dropIfExists('notification_template_versions');
        Schema::dropIfExists('notification_templates');
    }
};
