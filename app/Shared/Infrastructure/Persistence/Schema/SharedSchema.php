<?php

namespace App\Shared\Infrastructure\Persistence\Schema;

use Illuminate\Database\Schema\Blueprint;

final class SharedSchema
{
    public static function uuidPrimary(Blueprint $table, string $column = 'id'): void
    {
        /** @psalm-suppress UndefinedMagicMethod */
        $table->uuid($column)->primary();
    }

    public static function tenantColumn(Blueprint $table, bool $nullable = false): void
    {
        self::uuidColumn($table, 'tenant_id', $nullable, true);
    }

    public static function uuidColumn(Blueprint $table, string $column, bool $nullable = false, bool $indexed = false): void
    {
        $definition = $table->uuid($column);

        if ($nullable) {
            /** @psalm-suppress UndefinedMagicMethod */
            $definition->nullable();
        }

        if ($indexed) {
            $table->index($column);
        }
    }

    public static function requestContextColumns(Blueprint $table, bool $includeCausation = true, bool $nullable = false): void
    {
        self::uuidColumn($table, 'request_id', $nullable, true);
        self::uuidColumn($table, 'correlation_id', $nullable, true);

        if ($includeCausation) {
            self::uuidColumn($table, 'causation_id', $nullable, true);
        }
    }
}
