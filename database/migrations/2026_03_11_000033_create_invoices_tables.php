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
        Schema::create('invoice_number_sequences', function (Blueprint $table): void {
            $table->foreignUuid('tenant_id')->primary()->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('current_value')->default(0);
            $table->timestampsTz();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients');
            $table->string('invoice_number', 32);
            $table->string('patient_display_name', 255);
            $table->uuid('price_list_id')->nullable();
            $table->string('price_list_code', 120)->nullable();
            $table->string('price_list_name', 255)->nullable();
            $table->string('currency', 3);
            $table->date('invoice_date');
            $table->date('due_on')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 32)->index();
            $table->decimal('subtotal_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('finalized_at')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->json('last_transition')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'invoice_number'], 'invoices_tenant_invoice_number_unique');
            $table->index(['tenant_id', 'status', 'issued_at'], 'invoices_tenant_status_issued_index');
            $table->index(['tenant_id', 'patient_id', 'invoice_date'], 'invoices_tenant_patient_date_index');
            $table->index(['tenant_id', 'due_on'], 'invoices_tenant_due_on_index');
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->uuid('service_id');
            $table->string('service_code', 120);
            $table->string('service_name', 255);
            $table->string('service_category', 120)->nullable();
            $table->string('service_unit', 64)->nullable();
            $table->text('description')->nullable();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price_amount', 12, 2);
            $table->decimal('line_subtotal_amount', 12, 2);
            $table->string('currency', 3);
            $table->timestampsTz();

            $table->index(['tenant_id', 'invoice_id', 'created_at'], 'invoice_items_tenant_invoice_created_index');
            $table->index(['tenant_id', 'service_id'], 'invoice_items_tenant_service_index');
        });

        PostgresSchema::createPartialIndex(
            'invoices',
            'invoices_tenant_active_status_partial_idx',
            ['tenant_id', 'status', 'created_at'],
            '"deleted_at" IS NULL',
        );
    }

    public function down(): void
    {
        PostgresSchema::dropIndex('invoices_tenant_active_status_partial_idx');

        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_number_sequences');
    }
};
