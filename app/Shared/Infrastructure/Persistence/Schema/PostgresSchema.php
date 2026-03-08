<?php

namespace App\Shared\Infrastructure\Persistence\Schema;

use Illuminate\Support\Facades\DB;

final class PostgresSchema
{
    public static function ensurePgcryptoExtension(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');
    }

    /**
     * @param  list<string>  $columns
     */
    public static function createPartialIndex(string $table, string $name, array $columns, string $predicate): void
    {
        $quotedColumns = implode(', ', array_map(self::quoteIdentifier(...), $columns));
        $quotedTable = self::quoteIdentifier($table);
        $quotedIndexName = self::quoteIdentifier($name);

        DB::statement("CREATE INDEX {$quotedIndexName} ON {$quotedTable} ({$quotedColumns}) WHERE {$predicate}");
    }

    public static function dropIndex(string $name): void
    {
        $quotedIndexName = self::quoteIdentifier($name);

        DB::statement("DROP INDEX IF EXISTS {$quotedIndexName}");
    }

    private static function quoteIdentifier(string $identifier): string
    {
        $escaped = str_replace('"', '""', $identifier);

        return "\"{$escaped}\"";
    }
}
