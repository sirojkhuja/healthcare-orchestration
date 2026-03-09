<?php

use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('middle_name', 120)->nullable();
            $table->string('preferred_name', 120)->nullable();
            $table->string('provider_type', 32);
            $table->string('email', 255)->nullable();
            $table->string('phone', 32)->nullable();
            $table->text('notes')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['tenant_id', 'last_name', 'first_name'], 'providers_tenant_name_index');
            $table->index(['tenant_id', 'provider_type'], 'providers_tenant_type_index');
            $table->index(['tenant_id', 'clinic_id'], 'providers_tenant_clinic_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
