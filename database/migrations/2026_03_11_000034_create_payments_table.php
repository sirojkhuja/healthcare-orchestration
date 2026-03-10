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
        Schema::create('payments', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained('invoices');
            $table->string('invoice_number', 32);
            $table->string('provider_key', 64);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->text('description')->nullable();
            $table->string('status', 32)->index();
            $table->string('provider_payment_id', 191)->nullable();
            $table->string('provider_status', 120)->nullable();
            $table->text('checkout_url')->nullable();
            $table->string('failure_code', 120)->nullable();
            $table->text('failure_message')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->text('refund_reason')->nullable();
            $table->json('last_transition')->nullable();
            $table->timestampTz('initiated_at');
            $table->timestampTz('pending_at')->nullable();
            $table->timestampTz('captured_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('canceled_at')->nullable();
            $table->timestampTz('refunded_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'provider_key', 'provider_payment_id'], 'payments_tenant_provider_payment_unique');
            $table->index(['tenant_id', 'invoice_id', 'status'], 'payments_tenant_invoice_status_index');
            $table->index(['tenant_id', 'provider_key', 'status'], 'payments_tenant_provider_status_index');
        });

        PostgresSchema::createPartialIndex(
            'payments',
            'payments_tenant_active_status_partial_idx',
            ['tenant_id', 'status', 'created_at'],
            '"status" IN (\'initiated\', \'pending\', \'captured\')',
        );
    }

    public function down(): void
    {
        PostgresSchema::dropIndex('payments_tenant_active_status_partial_idx');

        Schema::dropIfExists('payments');
    }
};
