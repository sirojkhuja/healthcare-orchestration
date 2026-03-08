<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('assigns UUID primary keys to first-party Eloquent models', function (): void {
    $user = User::factory()->create();

    expect(Str::isUuid((string) $user->getKey()))->toBeTrue();
});

it('runs the migration suite cleanly on a fresh database and creates partial indexes', function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'sqlite',
        '--force' => true,
    ]);

    expect(Artisan::output())->toContain('DONE');
    expect(Schema::hasTable('users'))->toBeTrue();
    expect(Schema::hasTable('audit_events'))->toBeTrue();
    expect(Schema::hasTable('idempotency_requests'))->toBeTrue();

    /** @var list<object{name: string, sql: string|null}> $indexes */
    $indexes = DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'index'");
    $indexSqlByName = collect($indexes)->mapWithKeys(
        static fn (object $index): array => [$index->name => $index->sql],
    );

    expect($indexSqlByName->get('audit_events_tenant_occurred_partial_idx'))->toContain('WHERE "tenant_id" IS NOT NULL');
    expect($indexSqlByName->get('idempotency_requests_tenant_operation_expires_partial_idx'))->toContain('WHERE "tenant_id" IS NOT NULL');
});
