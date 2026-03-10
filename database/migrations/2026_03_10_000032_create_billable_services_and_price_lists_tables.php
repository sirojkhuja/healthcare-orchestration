<?php

use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billable_services', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 120);
            $table->string('name', 255);
            $table->string('category', 120)->nullable();
            $table->string('unit', 64)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'code'], 'billable_services_tenant_code_unique');
            $table->index(['tenant_id', 'is_active', 'name'], 'billable_services_tenant_active_name_index');
            $table->index(['tenant_id', 'category', 'name'], 'billable_services_tenant_category_name_index');
        });

        Schema::create('price_lists', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 120);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('currency', 3);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'code'], 'price_lists_tenant_code_unique');
            $table->index(['tenant_id', 'is_default', 'is_active'], 'price_lists_tenant_default_active_index');
            $table->index(['tenant_id', 'effective_from', 'effective_to'], 'price_lists_tenant_effective_window_index');
        });

        Schema::create('price_list_items', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained('billable_services')->restrictOnDelete();
            $table->decimal('unit_price_amount', 12, 2);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'price_list_id', 'service_id'], 'price_list_items_price_list_service_unique');
            $table->index(['tenant_id', 'price_list_id'], 'price_list_items_tenant_price_list_index');
            $table->index(['tenant_id', 'service_id'], 'price_list_items_tenant_service_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('billable_services');
    }
};
