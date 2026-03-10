<?php

namespace App\Modules\Billing\Presentation\Http\Controllers;

use App\Modules\Billing\Application\Commands\CreateInvoiceCommand;
use App\Modules\Billing\Application\Commands\DeleteInvoiceCommand;
use App\Modules\Billing\Application\Commands\UpdateInvoiceCommand;
use App\Modules\Billing\Application\Data\InvoiceSearchCriteria;
use App\Modules\Billing\Application\Handlers\CreateInvoiceCommandHandler;
use App\Modules\Billing\Application\Handlers\DeleteInvoiceCommandHandler;
use App\Modules\Billing\Application\Handlers\ExportInvoicesQueryHandler;
use App\Modules\Billing\Application\Handlers\GetInvoiceQueryHandler;
use App\Modules\Billing\Application\Handlers\ListInvoicesQueryHandler;
use App\Modules\Billing\Application\Handlers\SearchInvoicesQueryHandler;
use App\Modules\Billing\Application\Handlers\UpdateInvoiceCommandHandler;
use App\Modules\Billing\Application\Queries\ExportInvoicesQuery;
use App\Modules\Billing\Application\Queries\GetInvoiceQuery;
use App\Modules\Billing\Application\Queries\ListInvoicesQuery;
use App\Modules\Billing\Application\Queries\SearchInvoicesQuery;
use App\Modules\Billing\Domain\Invoices\InvoiceStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class InvoiceController
{
    public function create(Request $request, CreateInvoiceCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $invoice = $handler->handle(new CreateInvoiceCommand($validated));

        return response()->json([
            'status' => 'invoice_created',
            'data' => $invoice->toArray(),
        ], 201);
    }

    public function delete(string $invoiceId, DeleteInvoiceCommandHandler $handler): JsonResponse
    {
        $invoice = $handler->handle(new DeleteInvoiceCommand($invoiceId));

        return response()->json([
            'status' => 'invoice_deleted',
            'data' => $invoice->toArray(),
        ]);
    }

    public function export(Request $request, ExportInvoicesQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request, 1000, 1000);
        $validated = $request->validate($this->exportRules(1000));
        /** @var array<string, mixed> $validated */
        $format = array_key_exists('format', $validated) && is_string($validated['format'])
            ? $validated['format']
            : 'csv';
        $export = $handler->handle(new ExportInvoicesQuery($criteria, $format));

        return response()->json([
            'status' => 'invoice_export_created',
            'data' => $export->toArray(),
        ]);
    }

    public function list(Request $request, ListInvoicesQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request, 25, 100);

        return response()->json([
            'data' => array_map(
                static fn ($invoice): array => $invoice->toArray(),
                $handler->handle(new ListInvoicesQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function search(Request $request, SearchInvoicesQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request, 25, 100);

        return response()->json([
            'data' => array_map(
                static fn ($invoice): array => $invoice->toArray(),
                $handler->handle(new SearchInvoicesQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function show(string $invoiceId, GetInvoiceQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetInvoiceQuery($invoiceId))->toArray(),
        ]);
    }

    public function update(string $invoiceId, Request $request, UpdateInvoiceCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $invoice = $handler->handle(new UpdateInvoiceCommand($invoiceId, $validated));

        return response()->json([
            'status' => 'invoice_updated',
            'data' => $invoice->toArray(),
        ]);
    }

    private function criteria(Request $request, int $defaultLimit, int $maxLimit): InvoiceSearchCriteria
    {
        $validated = $request->validate($this->listRules($maxLimit));
        /** @var array<string, mixed> $validated */
        $this->assertDateRange($validated, 'issued_from', 'issued_to', 'issued_at');
        $this->assertDateRange($validated, 'due_from', 'due_to', 'due_on');
        $this->assertDateRange($validated, 'created_from', 'created_to', 'created_at');

        return new InvoiceSearchCriteria(
            query: $this->stringValue($validated, 'q'),
            status: $this->stringValue($validated, 'status'),
            patientId: $this->stringValue($validated, 'patient_id'),
            issuedFrom: $this->stringValue($validated, 'issued_from'),
            issuedTo: $this->stringValue($validated, 'issued_to'),
            dueFrom: $this->stringValue($validated, 'due_from'),
            dueTo: $this->stringValue($validated, 'due_to'),
            createdFrom: $this->stringValue($validated, 'created_from'),
            createdTo: $this->stringValue($validated, 'created_to'),
            limit: array_key_exists('limit', $validated) && is_numeric($validated['limit'])
                ? (int) $validated['limit']
                : $defaultLimit,
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function createRules(): array
    {
        return [
            'patient_id' => ['required', 'uuid'],
            'price_list_id' => ['sometimes', 'nullable', 'uuid'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3', 'alpha'],
            'invoice_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'due_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function exportRules(int $maxLimit): array
    {
        return $this->listRules($maxLimit) + [
            'format' => ['sometimes', 'string', 'in:csv'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function listRules(int $maxLimit): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:'.implode(',', InvoiceStatus::all())],
            'patient_id' => ['nullable', 'uuid'],
            'issued_from' => ['nullable', 'date'],
            'issued_to' => ['nullable', 'date'],
            'due_from' => ['nullable', 'date_format:Y-m-d'],
            'due_to' => ['nullable', 'date_format:Y-m-d'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.$maxLimit],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function updateRules(): array
    {
        return [
            'patient_id' => ['sometimes', 'filled', 'uuid'],
            'price_list_id' => ['sometimes', 'nullable', 'uuid'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3', 'alpha'],
            'invoice_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'due_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function assertNonEmptyPatch(array $validated): void
    {
        if ($validated === []) {
            throw ValidationException::withMessages([
                'payload' => ['At least one updatable field is required.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertDateRange(array $validated, string $fromKey, string $toKey, string $errorKey): void
    {
        $from = $this->stringValue($validated, $fromKey);
        $to = $this->stringValue($validated, $toKey);

        if ($from !== null && $to !== null && CarbonImmutable::parse($from)->greaterThan(CarbonImmutable::parse($to))) {
            throw ValidationException::withMessages([
                $errorKey => ['The end date must be on or after the start date.'],
            ]);
        }
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
