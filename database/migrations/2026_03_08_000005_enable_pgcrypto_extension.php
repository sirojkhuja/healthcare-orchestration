<?php

use App\Shared\Infrastructure\Persistence\Schema\PostgresSchema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        PostgresSchema::ensurePgcryptoExtension();
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'pgsql') {
            return;
        }

        \Illuminate\Support\Facades\DB::statement('DROP EXTENSION IF EXISTS pgcrypto');
    }
};
