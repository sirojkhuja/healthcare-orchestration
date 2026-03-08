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
        Schema::create('audit_events', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            SharedSchema::tenantColumn($table, nullable: true);
            $table->string('action');
            $table->string('object_type');
            $table->string('object_id');
            $table->string('actor_type');
            SharedSchema::uuidColumn($table, 'actor_id', nullable: true, indexed: true);
            $table->string('actor_name')->nullable();
            SharedSchema::requestContextColumns($table, includeCausation: false);
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('occurred_at')->index();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['object_type', 'object_id', 'occurred_at']);
        });

        PostgresSchema::createPartialIndex(
            table: 'audit_events',
            name: 'audit_events_tenant_occurred_partial_idx',
            columns: ['tenant_id', 'occurred_at'],
            predicate: '"tenant_id" IS NOT NULL',
        );
    }

    public function down(): void
    {
        PostgresSchema::dropIndex('audit_events_tenant_occurred_partial_idx');
        Schema::dropIfExists('audit_events');
    }
};
