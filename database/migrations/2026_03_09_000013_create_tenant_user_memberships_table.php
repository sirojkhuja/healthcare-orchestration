<?php

use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_user_memberships', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            SharedSchema::tenantColumn($table);
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('active');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'user_id'], 'tenant_user_memberships_scope_unique');
            $table->index(['tenant_id', 'status'], 'tenant_user_memberships_tenant_status_index');
            $table->index(['tenant_id', 'updated_at'], 'tenant_user_memberships_tenant_updated_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user_memberships');
    }
};
