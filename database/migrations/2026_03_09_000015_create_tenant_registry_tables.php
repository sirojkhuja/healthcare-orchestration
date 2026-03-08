<?php

use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->string('name', 160);
            $table->string('status', 32)->default('active')->index();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->timestampTz('activated_at')->nullable()->index();
            $table->timestampTz('suspended_at')->nullable()->index();
            $table->timestampsTz();

            $table->index(['status', 'updated_at'], 'tenants_status_updated_index');
        });

        Schema::create('tenant_settings', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->string('locale', 16)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestampsTz();
        });

        Schema::create('tenant_limits', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->unsignedInteger('users')->nullable();
            $table->unsignedInteger('clinics')->nullable();
            $table->unsignedInteger('providers')->nullable();
            $table->unsignedInteger('patients')->nullable();
            $table->decimal('storage_gb', 12, 2)->nullable();
            $table->unsignedInteger('monthly_notifications')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_limits');
        Schema::dropIfExists('tenant_settings');
        Schema::dropIfExists('tenants');
    }
};
