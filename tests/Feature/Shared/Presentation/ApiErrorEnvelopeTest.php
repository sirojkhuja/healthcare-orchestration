<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

beforeEach(function (): void {
    Route::middleware('api')->group(function (): void {
        Route::get('/api/v1/_tests/errors/validation', function (): void {
            Validator::make([], [
                'name' => ['required', 'string'],
            ])->validate();
        });

        Route::get('/api/v1/_tests/errors/conflict', function (): void {
            throw new ConflictHttpException('The request conflicts with the current system state.');
        });

        Route::get('/api/v1/_tests/errors/internal', function (): void {
            throw new \RuntimeException('boom');
        });

        Route::get('/api/v1/_tests/errors/tenant-required', function () {
            return response()->json(['status' => 'ok']);
        })->middleware('tenant.require');

        Route::get('/api/v1/_tests/errors/tenants/{tenantId}/tenant-required', function () {
            return response()->json(['status' => 'ok']);
        })->middleware('tenant.require');
    });
});

it('returns the standard error envelope for validation failures', function (): void {
    $correlationId = (string) Str::uuid();

    $response = $this->withHeader('X-Correlation-Id', $correlationId)
        ->getJson('/api/v1/_tests/errors/validation');

    $response
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonPath('message', 'The request payload is invalid.')
        ->assertJsonStructure([
            'code',
            'message',
            'details' => ['errors' => ['name']],
            'trace_id',
            'correlation_id',
        ])
        ->assertJsonPath('correlation_id', strtolower($correlationId))
        ->assertHeader('X-Correlation-Id', strtolower($correlationId));
});

it('maps tenant context failures into documented error codes', function (): void {
    $tenantId = (string) Str::uuid();
    $otherTenantId = (string) Str::uuid();

    $missingContext = $this->getJson('/api/v1/_tests/errors/tenant-required');

    $missingContext
        ->assertStatus(400)
        ->assertJsonPath('code', 'TENANT_CONTEXT_REQUIRED')
        ->assertJsonPath('message', 'Tenant context is required for this request.');

    $scopeViolation = $this->withHeader('X-Tenant-Id', $tenantId)
        ->getJson("/api/v1/_tests/errors/tenants/{$otherTenantId}/tenant-required");

    $scopeViolation
        ->assertStatus(403)
        ->assertJsonPath('code', 'TENANT_SCOPE_VIOLATION')
        ->assertJsonPath('message', 'Tenant route scope does not match the request tenant context.');
});

it('maps missing routes and unexpected exceptions into the standard error envelope', function (): void {
    $missingRoute = $this->getJson('/api/v1/_tests/errors/missing-route');

    $missingRoute
        ->assertStatus(404)
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND')
        ->assertJsonPath('message', 'The requested resource does not exist in the current tenant scope.');

    $internalError = $this->getJson('/api/v1/_tests/errors/internal');

    $internalError
        ->assertStatus(500)
        ->assertJsonPath('code', 'INTERNAL_ERROR')
        ->assertJsonPath('message', 'An unexpected error occurred.')
        ->assertJsonStructure([
            'code',
            'message',
            'details',
            'trace_id',
            'correlation_id',
        ]);
});

it('maps documented conflict exceptions into the standard error envelope', function (): void {
    $response = $this->getJson('/api/v1/_tests/errors/conflict');

    $response
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT')
        ->assertJsonPath('message', 'The request conflicts with the current system state.');
});
