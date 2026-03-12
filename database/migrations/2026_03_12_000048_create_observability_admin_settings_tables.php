<?php

use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_feature_flags', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('flag_key', 64);
            $table->boolean('enabled');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'flag_key'], 'ops_feature_flags_tenant_flag_unique');
            $table->index(['tenant_id', 'updated_at'], 'ops_feature_flags_tenant_updated_index');
        });

        Schema::create('ops_rate_limits', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('bucket_key', 96);
            $table->unsignedInteger('requests_per_minute');
            $table->unsignedInteger('burst');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'bucket_key'], 'ops_rate_limits_tenant_bucket_unique');
            $table->index(['tenant_id', 'updated_at'], 'ops_rate_limits_tenant_updated_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_rate_limits');
        Schema::dropIfExists('ops_feature_flags');
    }
};
