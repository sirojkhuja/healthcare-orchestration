<?php

use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('key_prefix', 24);
            $table->string('token_hash', 64)->unique();
            $table->timestampTz('last_used_at')->nullable()->index();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampTz('revoked_at')->nullable()->index();
            $table->timestampsTz();

            $table->index(['user_id', 'created_at'], 'api_keys_user_created_index');
        });

        Schema::create('registered_devices', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('installation_id', 128);
            $table->string('name');
            $table->string('platform', 32);
            $table->string('push_token')->nullable();
            $table->string('app_version', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('last_seen_at')->nullable()->index();
            $table->timestampsTz();

            $table->unique(['user_id', 'installation_id'], 'registered_devices_user_installation_unique');
            $table->index(['user_id', 'updated_at'], 'registered_devices_user_updated_index');
        });

        Schema::create('tenant_ip_allowlist_entries', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            SharedSchema::tenantColumn($table);
            $table->string('cidr', 64);
            $table->string('label', 120)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'cidr'], 'tenant_ip_allowlist_entries_tenant_cidr_unique');
            $table->index(['tenant_id', 'position'], 'tenant_ip_allowlist_entries_tenant_position_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_ip_allowlist_entries');
        Schema::dropIfExists('registered_devices');
        Schema::dropIfExists('api_keys');
    }
};
