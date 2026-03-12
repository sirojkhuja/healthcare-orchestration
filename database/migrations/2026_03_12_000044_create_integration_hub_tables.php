<?php

use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_states', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('integration_key', 64);
            $table->boolean('enabled')->default(false);
            $table->string('last_test_status', 32)->nullable();
            $table->text('last_test_message')->nullable();
            $table->timestampTz('last_tested_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'integration_key'], 'integration_states_tenant_key_unique');
            $table->index(['tenant_id', 'enabled'], 'integration_states_tenant_enabled_index');
        });

        Schema::create('integration_credentials', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('integration_key', 64);
            $table->longText('credential_payload');
            $table->json('configured_fields');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'integration_key'], 'integration_credentials_tenant_key_unique');
        });

        Schema::create('integration_logs', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('integration_key', 64);
            $table->string('level', 16);
            $table->string('event', 120);
            $table->text('message');
            $table->json('context');
            $table->timestampTz('created_at');

            $table->index(['tenant_id', 'integration_key', 'created_at'], 'integration_logs_tenant_key_created_index');
            $table->index(['tenant_id', 'integration_key', 'level'], 'integration_logs_tenant_key_level_index');
        });

        Schema::create('integration_webhooks', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('integration_key', 64);
            $table->string('name', 64);
            $table->string('endpoint_url', 2048);
            $table->string('auth_mode', 64);
            $table->longText('secret')->nullable();
            $table->string('secret_hash', 64)->nullable();
            $table->timestampTz('secret_last_rotated_at')->nullable();
            $table->string('status', 32)->default('active');
            $table->json('metadata');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'integration_key', 'name'], 'integration_webhooks_tenant_key_name_unique');
            $table->index(['tenant_id', 'integration_key', 'status'], 'integration_webhooks_tenant_key_status_index');
        });

        Schema::create('integration_tokens', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('integration_key', 64);
            $table->string('label', 64)->default('primary');
            $table->longText('access_token')->nullable();
            $table->longText('refresh_token')->nullable();
            $table->string('token_type', 32)->default('Bearer');
            $table->json('scopes');
            $table->timestampTz('access_token_expires_at')->nullable();
            $table->timestampTz('refresh_token_expires_at')->nullable();
            $table->timestampTz('last_refreshed_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->json('metadata');
            $table->timestampsTz();

            $table->index(['tenant_id', 'integration_key', 'revoked_at'], 'integration_tokens_tenant_key_revoked_index');
            $table->index(['tenant_id', 'integration_key', 'created_at'], 'integration_tokens_tenant_key_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_tokens');
        Schema::dropIfExists('integration_webhooks');
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('integration_credentials');
        Schema::dropIfExists('integration_states');
    }
};
