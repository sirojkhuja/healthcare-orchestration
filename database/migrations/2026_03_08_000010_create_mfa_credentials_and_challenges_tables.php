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
        Schema::create('mfa_credentials', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->text('secret')->nullable();
            $table->json('recovery_code_hashes')->nullable();
            $table->timestampTz('enabled_at')->nullable()->index();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('disabled_at')->nullable()->index();
            $table->timestampsTz();

            $table->index(['user_id', 'enabled_at'], 'mfa_credentials_user_enabled_index');
        });

        Schema::create('mfa_challenges', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose')->default('login');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('verified_at')->nullable()->index();
            $table->timestampsTz();

            $table->index(['user_id', 'verified_at'], 'mfa_challenges_user_verified_index');
        });

        PostgresSchema::createPartialIndex(
            table: 'mfa_challenges',
            name: 'mfa_challenges_active_partial_idx',
            columns: ['user_id', 'expires_at'],
            predicate: '"verified_at" IS NULL',
        );
    }

    public function down(): void
    {
        PostgresSchema::dropIndex('mfa_challenges_active_partial_idx');
        Schema::dropIfExists('mfa_challenges');
        Schema::dropIfExists('mfa_credentials');
    }
};
