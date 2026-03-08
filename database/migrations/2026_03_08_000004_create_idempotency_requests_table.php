<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('scope_hash', 64);
            $table->string('operation');
            $table->uuid('tenant_id')->nullable()->index();
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
            $table->index(['operation', 'tenant_id'], 'idempotency_requests_operation_tenant_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_requests');
    }
};
