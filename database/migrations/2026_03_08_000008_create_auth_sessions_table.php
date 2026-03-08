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
        Schema::create('auth_sessions', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('access_token_id');
            $table->string('refresh_token_hash', 64)->unique();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('access_token_expires_at')->index();
            $table->timestampTz('refresh_token_expires_at')->index();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('revoked_at')->nullable()->index();
            $table->timestampsTz();

            $table->index(['user_id', 'revoked_at'], 'auth_sessions_user_revoked_index');
            $table->index(['id', 'access_token_id'], 'auth_sessions_access_lookup_index');
        });

        PostgresSchema::createPartialIndex(
            table: 'auth_sessions',
            name: 'auth_sessions_user_active_partial_idx',
            columns: ['user_id', 'refresh_token_expires_at'],
            predicate: '"revoked_at" IS NULL',
        );
    }

    public function down(): void
    {
        PostgresSchema::dropIndex('auth_sessions_user_active_partial_idx');
        Schema::dropIfExists('auth_sessions');
    }
};
