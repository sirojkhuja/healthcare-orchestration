<?php

use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_availability_rules', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->string('scope_type', 16);
            $table->string('availability_type', 16);
            $table->string('weekday', 16)->nullable();
            $table->date('specific_date')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'provider_id'], 'provider_availability_rules_tenant_provider_index');
            $table->index(
                ['tenant_id', 'provider_id', 'scope_type', 'availability_type', 'weekday'],
                'provider_availability_rules_weekly_lookup_index',
            );
            $table->index(
                ['tenant_id', 'provider_id', 'specific_date'],
                'provider_availability_rules_date_lookup_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_availability_rules');
    }
};
