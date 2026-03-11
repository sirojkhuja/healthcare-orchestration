<?php

use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_reconciliation_runs', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('provider_key', 64)->index();
            $table->json('requested_payment_ids')->nullable();
            $table->unsignedInteger('scanned_count');
            $table->unsignedInteger('changed_count');
            $table->unsignedInteger('result_count');
            $table->json('results');
            $table->timestampsTz();

            $table->index(['tenant_id', 'provider_key', 'created_at'], 'payment_reconciliation_runs_tenant_provider_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliation_runs');
    }
};
