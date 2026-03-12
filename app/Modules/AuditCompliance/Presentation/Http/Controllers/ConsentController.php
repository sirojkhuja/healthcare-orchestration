<?php

namespace App\Modules\AuditCompliance\Presentation\Http\Controllers;

use App\Modules\AuditCompliance\Application\Data\ConsentViewData;
use App\Modules\AuditCompliance\Application\Data\ConsentViewSearchCriteria;
use App\Modules\AuditCompliance\Application\Handlers\GetConsentQueryHandler;
use App\Modules\AuditCompliance\Application\Handlers\ListConsentsQueryHandler;
use App\Modules\AuditCompliance\Application\Queries\GetConsentQuery;
use App\Modules\AuditCompliance\Application\Queries\ListConsentsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ConsentController
{
    public function list(Request $request, ListConsentsQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'patient_id' => ['nullable', 'uuid'],
            'consent_type' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'string', 'in:active,expired,revoked'],
            'granted_from' => ['nullable', 'date'],
            'granted_to' => ['nullable', 'date'],
            'expires_from' => ['nullable', 'date'],
            'expires_to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        /** @var array<string, mixed> $validated */
        $criteria = new ConsentViewSearchCriteria(
            query: $this->stringValue($validated, 'q'),
            patientId: $this->stringValue($validated, 'patient_id'),
            consentType: $this->normalizedIdentifier($validated, 'consent_type'),
            status: $this->stringValue($validated, 'status'),
            grantedFrom: $this->dateValue($validated, 'granted_from'),
            grantedTo: $this->dateValue($validated, 'granted_to'),
            expiresFrom: $this->dateValue($validated, 'expires_from'),
            expiresTo: $this->dateValue($validated, 'expires_to'),
            limit: $this->integerValue($validated, 'limit', 50),
        );

        $this->assertRange($criteria->grantedFrom, $criteria->grantedTo, 'granted_at');
        $this->assertRange($criteria->expiresFrom, $criteria->expiresTo, 'expires_at');

        return response()->json([
            'data' => array_map(
                static fn (ConsentViewData $consent): array => $consent->toArray(),
                $handler->handle(new ListConsentsQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function show(string $consentId, GetConsentQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetConsentQuery($consentId))->toArray(),
        ]);
    }

    private function assertRange(?CarbonImmutable $from, ?CarbonImmutable $to, string $field): void
    {
        if ($from instanceof CarbonImmutable && $to instanceof CarbonImmutable && $from->gt($to)) {
            throw ValidationException::withMessages([
                $field => ['The end timestamp must be on or after the start timestamp.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function dateValue(array $validated, string $key): ?CarbonImmutable
    {
        $value = $this->stringValue($validated, $key);

        return $value !== null ? CarbonImmutable::parse($value) : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function integerValue(array $validated, string $key, int $default): int
    {
        return array_key_exists($key, $validated) && is_numeric($validated[$key])
            ? (int) $validated[$key]
            : $default;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function normalizedIdentifier(array $validated, string $key): ?string
    {
        $value = $this->stringValue($validated, $key);

        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($value));
        $result = trim(is_string($normalized) ? $normalized : '', '_');

        return $result !== '' ? $result : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function stringValue(array $validated, string $key): ?string
    {
        return array_key_exists($key, $validated) && is_string($validated[$key]) && $validated[$key] !== ''
            ? $validated[$key]
            : null;
    }
}
