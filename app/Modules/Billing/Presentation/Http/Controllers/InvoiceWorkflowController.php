<?php

namespace App\Modules\Billing\Presentation\Http\Controllers;

use App\Modules\Billing\Application\Commands\FinalizeInvoiceCommand;
use App\Modules\Billing\Application\Commands\IssueInvoiceCommand;
use App\Modules\Billing\Application\Commands\VoidInvoiceCommand;
use App\Modules\Billing\Application\Handlers\FinalizeInvoiceCommandHandler;
use App\Modules\Billing\Application\Handlers\IssueInvoiceCommandHandler;
use App\Modules\Billing\Application\Handlers\VoidInvoiceCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InvoiceWorkflowController
{
    public function finalize(string $invoiceId, FinalizeInvoiceCommandHandler $handler): JsonResponse
    {
        $invoice = $handler->handle(new FinalizeInvoiceCommand($invoiceId));

        return response()->json([
            'status' => 'invoice_finalized',
            'data' => $invoice->toArray(),
        ]);
    }

    public function issue(string $invoiceId, IssueInvoiceCommandHandler $handler): JsonResponse
    {
        $invoice = $handler->handle(new IssueInvoiceCommand($invoiceId));

        return response()->json([
            'status' => 'invoice_issued',
            'data' => $invoice->toArray(),
        ]);
    }

    public function void(string $invoiceId, Request $request, VoidInvoiceCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:5000'],
        ]);
        /** @var array{reason: string} $validated */
        $invoice = $handler->handle(new VoidInvoiceCommand($invoiceId, trim($validated['reason'])));

        return response()->json([
            'status' => 'invoice_voided',
            'data' => $invoice->toArray(),
        ]);
    }
}
