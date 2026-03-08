<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->string('action');
            $table->string('object_type');
            $table->string('object_id');
            $table->string('actor_type');
            $table->uuid('actor_id')->nullable()->index();
            $table->string('actor_name')->nullable();
            $table->uuid('request_id')->index();
            $table->uuid('correlation_id')->index();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('occurred_at')->index();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['object_type', 'object_id', 'occurred_at']);
            $table->index(['tenant_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
