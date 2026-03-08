<?php

use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kafka_consumer_receipts', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->string('consumer_name');
            $table->string('message_id');
            $table->string('topic');
            $table->unsignedInteger('partition');
            $table->timestampTz('processed_at')->index();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['consumer_name', 'message_id'], 'kafka_consumer_receipts_consumer_message_unique');
            $table->index(['consumer_name', 'processed_at'], 'kafka_consumer_receipts_consumer_processed_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kafka_consumer_receipts');
    }
};
