<?php

use App\Shared\Infrastructure\Persistence\Schema\SharedSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_participants', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->string('participant_type', 32);
            $table->uuid('reference_id')->nullable();
            $table->string('display_name', 160);
            $table->string('role', 120);
            $table->boolean('required')->default(false);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'appointment_id'], 'appointment_participants_tenant_appointment_index');
        });

        Schema::create('appointment_notes', function (Blueprint $table): void {
            SharedSchema::uuidPrimary($table);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignUuid('author_user_id')->constrained('users');
            $table->string('author_name', 120);
            $table->string('author_email', 255);
            $table->text('body');
            $table->timestampsTz();

            $table->index(['tenant_id', 'appointment_id'], 'appointment_notes_tenant_appointment_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_notes');
        Schema::dropIfExists('appointment_participants');
    }
};
