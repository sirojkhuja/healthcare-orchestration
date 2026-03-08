<?php

use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            SharedSchema::tenantColumn($table);
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'name'], 'roles_tenant_name_index');
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('permission');
            $table->timestampsTz();

            $table->unique(['role_id', 'permission'], 'role_permissions_role_permission_unique');
        });

        Schema::create('user_role_assignments', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            SharedSchema::tenantColumn($table);
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'user_id', 'role_id'], 'user_role_assignments_scope_unique');
            $table->index(['tenant_id', 'user_id'], 'user_role_assignments_tenant_user_index');
            $table->index(['tenant_id', 'role_id'], 'user_role_assignments_tenant_role_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_assignments');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
    }
};
