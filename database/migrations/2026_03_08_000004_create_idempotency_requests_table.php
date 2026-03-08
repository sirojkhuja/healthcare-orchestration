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
        Schema::create('idempotency_requests', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->string('scope_hash', 64);
            $table->string('operation');
            SharedSchema::tenantColumn($table, nullable: true);
            $table->string('actor_id')->nullable()->index();
            $table->string('idempotency_key');
            $table->string('request_fingerprint', 64);
            $table->string('status', 32);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_headers')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('expires_at')->index();
            $table->timestampsTz();

            $table->unique(['scope_hash', 'idempotency_key'], 'idempotency_requests_scope_key_unique');
        });

        PostgresSchema::createPartialIndex(
            table: 'idempotency_requests',
            name: 'idempotency_requests_tenant_operation_expires_partial_idx',
            columns: ['tenant_id', 'operation', 'expires_at'],
            predicate: '"tenant_id" IS NOT NULL',
        );
    }

    public function down(): void
    {
        PostgresSchema::dropIndex('idempotency_requests_tenant_operation_expires_partial_idx');
        Schema::dropIfExists('idempotency_requests');
    }
};
