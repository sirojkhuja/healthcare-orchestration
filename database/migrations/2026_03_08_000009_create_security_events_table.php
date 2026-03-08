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
        Schema::create('security_events', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            SharedSchema::tenantColumn($table, nullable: true);
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type');
            $table->string('subject_type');
            $table->string('subject_id');
            $table->string('actor_type');
            SharedSchema::uuidColumn($table, 'actor_id', nullable: true, indexed: true);
            $table->string('actor_name')->nullable();
            SharedSchema::requestContextColumns($table, includeCausation: false);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('occurred_at')->index();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['user_id', 'occurred_at'], 'security_events_user_occurred_index');
            $table->index(['subject_type', 'subject_id', 'occurred_at'], 'security_events_subject_occurred_index');
            $table->index(['event_type', 'occurred_at'], 'security_events_type_occurred_index');
        });

        PostgresSchema::createPartialIndex(
            table: 'security_events',
            name: 'security_events_tenant_occurred_partial_idx',
            columns: ['tenant_id', 'occurred_at'],
            predicate: '"tenant_id" IS NOT NULL',
        );
    }

    public function down(): void
    {
        PostgresSchema::dropIndex('security_events_tenant_occurred_partial_idx');
        Schema::dropIfExists('security_events');
    }
};
