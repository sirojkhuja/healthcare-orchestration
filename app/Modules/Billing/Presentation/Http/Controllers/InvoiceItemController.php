<?php

namespace App\Modules\Billing\Presentation\Http\Controllers;

use App\Modules\Billing\Application\Commands\AddInvoiceItemCommand;
use App\Modules\Billing\Application\Commands\RemoveInvoiceItemCommand;
use App\Modules\Billing\Application\Commands\UpdateInvoiceItemCommand;
use App\Modules\Billing\Application\Handlers\AddInvoiceItemCommandHandler;
use App\Modules\Billing\Application\Handlers\ListInvoiceItemsQueryHandler;
use App\Modules\Billing\Application\Handlers\RemoveInvoiceItemCommandHandler;
use App\Modules\Billing\Application\Handlers\UpdateInvoiceItemCommandHandler;
use App\Modules\Billing\Application\Queries\ListInvoiceItemsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class InvoiceItemController
{
    public function create(string $invoiceId, Request $request, AddInvoiceItemCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $invoice = $handler->handle(new AddInvoiceItemCommand($invoiceId, $validated));

        return response()->json([
            'status' => 'invoice_item_created',
            'data' => $invoice->toArray(),
        ], 201);
    }

    public function delete(string $invoiceId, string $itemId, RemoveInvoiceItemCommandHandler $handler): JsonResponse
    {
        $invoice = $handler->handle(new RemoveInvoiceItemCommand($invoiceId, $itemId));

        return response()->json([
            'status' => 'invoice_item_deleted',
            'data' => $invoice->toArray(),
        ]);
    }

    public function list(string $invoiceId, ListInvoiceItemsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($item): array => $item->toArray(),
                $handler->handle(new ListInvoiceItemsQuery($invoiceId)),
            ),
        ]);
    }

    public function update(
        string $invoiceId,
        string $itemId,
        Request $request,
        UpdateInvoiceItemCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $invoice = $handler->handle(new UpdateInvoiceItemCommand($invoiceId, $itemId, $validated));

        return response()->json([
            'status' => 'invoice_item_updated',
            'data' => $invoice->toArray(),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function createRules(): array
    {
        return [
            'service_id' => ['required', 'uuid'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'quantity' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'unit_price_amount' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function updateRules(): array
    {
        return [
            'service_id' => ['sometimes', 'filled', 'uuid'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'quantity' => ['sometimes', 'numeric', 'gt:0', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'unit_price_amount' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
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
}
