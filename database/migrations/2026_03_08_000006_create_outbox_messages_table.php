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
        Schema::create('outbox_messages', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->uuid('event_id')->unique();
            $table->string('event_type');
            $table->string('topic');
            SharedSchema::tenantColumn($table, nullable: true);
            SharedSchema::requestContextColumns($table);
            $table->string('partition_key')->nullable();
            $table->json('headers')->nullable();
            $table->json('payload');
            $table->string('status', 32)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('next_attempt_at')->nullable()->index();
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'next_attempt_at'], 'outbox_messages_status_next_attempt_index');
            $table->index(['topic', 'status'], 'outbox_messages_topic_status_index');
        });

        PostgresSchema::createPartialIndex(
            table: 'outbox_messages',
            name: 'outbox_messages_tenant_status_next_attempt_partial_idx',
            columns: ['tenant_id', 'status', 'next_attempt_at'],
            predicate: '"tenant_id" IS NOT NULL',
        );
    }

    public function down(): void
    {
        PostgresSchema::dropIndex('outbox_messages_tenant_status_next_attempt_partial_idx');
        Schema::dropIfExists('outbox_messages');
    }
};
