<?php

namespace App\Shared\Presentation\Http\Controllers;

use App\Shared\Application\Data\ReferenceEntryData;
use App\Shared\Application\Handlers\ListCountriesQueryHandler;
use App\Shared\Application\Handlers\ListCurrenciesQueryHandler;
use App\Shared\Application\Handlers\ListDiagnosisCodesQueryHandler;
use App\Shared\Application\Handlers\ListInsuranceCodesQueryHandler;
use App\Shared\Application\Handlers\ListLanguagesQueryHandler;
use App\Shared\Application\Handlers\ListProcedureCodesQueryHandler;
use App\Shared\Application\Queries\ListCountriesQuery;
use App\Shared\Application\Queries\ListCurrenciesQuery;
use App\Shared\Application\Queries\ListDiagnosisCodesQuery;
use App\Shared\Application\Queries\ListInsuranceCodesQuery;
use App\Shared\Application\Queries\ListLanguagesQuery;
use App\Shared\Application\Queries\ListProcedureCodesQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReferenceCatalogController
{
    public function countries(Request $request, ListCountriesQueryHandler $handler): JsonResponse
    {
        return $this->respond($request, fn (?string $query, int $limit) => $handler->handle(new ListCountriesQuery($query, $limit)));
    }

    public function currencies(Request $request, ListCurrenciesQueryHandler $handler): JsonResponse
    {
        return $this->respond($request, fn (?string $query, int $limit) => $handler->handle(new ListCurrenciesQuery($query, $limit)));
    }

    public function diagnosisCodes(Request $request, ListDiagnosisCodesQueryHandler $handler): JsonResponse
    {
        return $this->respond($request, fn (?string $query, int $limit) => $handler->handle(new ListDiagnosisCodesQuery($query, $limit)));
    }

    public function insuranceCodes(Request $request, ListInsuranceCodesQueryHandler $handler): JsonResponse
    {
        return $this->respond($request, fn (?string $query, int $limit) => $handler->handle(new ListInsuranceCodesQuery($query, $limit)));
    }

    public function languages(Request $request, ListLanguagesQueryHandler $handler): JsonResponse
    {
        return $this->respond($request, fn (?string $query, int $limit) => $handler->handle(new ListLanguagesQuery($query, $limit)));
    }

    public function procedureCodes(Request $request, ListProcedureCodesQueryHandler $handler): JsonResponse
    {
        return $this->respond($request, fn (?string $query, int $limit) => $handler->handle(new ListProcedureCodesQuery($query, $limit)));
    }

    /**
     * @param  callable(?string, int): list<ReferenceEntryData>  $resolver
     */
    private function respond(Request $request, callable $resolver): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        /** @var array<string, mixed> $validated */
        /** @var string|null $query */
        $query = $validated['q'] ?? null;
        $limit = $this->integerValue($validated['limit'] ?? null, 25);
        /** @var list<ReferenceEntryData> $entries */
        $entries = $resolver($query, $limit);

        return response()->json([
            'data' => array_map(
                static fn (ReferenceEntryData $entry): array => $entry->toArray(),
                $entries,
            ),
            'meta' => [
                'filters' => [
                    'q' => $query,
                    'limit' => $limit,
                ],
            ],
        ]);
    }

    private function integerValue(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        return $default;
    }
}
