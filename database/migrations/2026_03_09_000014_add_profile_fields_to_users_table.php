<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 32)->nullable()->after('email');
            $table->string('job_title', 120)->nullable()->after('phone');
            $table->string('locale', 16)->nullable()->after('job_title');
            $table->string('timezone', 64)->nullable()->after('locale');
            $table->string('avatar_disk', 64)->nullable()->after('timezone');
            $table->string('avatar_path')->nullable()->after('avatar_disk');
            $table->string('avatar_file_name')->nullable()->after('avatar_path');
            $table->string('avatar_mime_type', 128)->nullable()->after('avatar_file_name');
            $table->unsignedBigInteger('avatar_size_bytes')->nullable()->after('avatar_mime_type');
            $table->timestampTz('avatar_uploaded_at')->nullable()->after('avatar_size_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'phone',
                'job_title',
                'locale',
                'timezone',
                'avatar_disk',
                'avatar_path',
                'avatar_file_name',
                'avatar_mime_type',
                'avatar_size_bytes',
                'avatar_uploaded_at',
            ]);
        });
    }
};
