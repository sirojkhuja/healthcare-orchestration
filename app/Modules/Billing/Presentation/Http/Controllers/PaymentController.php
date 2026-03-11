<?php

namespace App\Modules\Billing\Presentation\Http\Controllers;

use App\Modules\Billing\Application\Commands\InitiatePaymentCommand;
use App\Modules\Billing\Application\Data\PaymentListCriteria;
use App\Modules\Billing\Application\Handlers\GetPaymentQueryHandler;
use App\Modules\Billing\Application\Handlers\GetPaymentStatusQueryHandler;
use App\Modules\Billing\Application\Handlers\InitiatePaymentCommandHandler;
use App\Modules\Billing\Application\Handlers\ListPaymentsQueryHandler;
use App\Modules\Billing\Application\Queries\GetPaymentQuery;
use App\Modules\Billing\Application\Queries\GetPaymentStatusQuery;
use App\Modules\Billing\Application\Queries\ListPaymentsQuery;
use App\Modules\Billing\Domain\Payments\PaymentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PaymentController
{
    public function initiate(Request $request, InitiatePaymentCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->initiateRules());
        /** @var array<string, mixed> $validated */
        $payment = $handler->handle(new InitiatePaymentCommand($validated));

        return response()->json([
            'status' => 'payment_initiated',
            'data' => $payment->toArray(),
        ], 201);
    }

    public function list(Request $request, ListPaymentsQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request, 25, 100);

        return response()->json([
            'data' => array_map(
                static fn ($payment): array => $payment->toArray(),
                $handler->handle(new ListPaymentsQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function show(string $paymentId, GetPaymentQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetPaymentQuery($paymentId))->toArray(),
        ]);
    }

    public function status(string $paymentId, GetPaymentStatusQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetPaymentStatusQuery($paymentId))->toStatusArray(),
        ]);
    }

    private function criteria(Request $request, int $defaultLimit, int $maxLimit): PaymentListCriteria
    {
        $validated = $request->validate($this->listRules($maxLimit));
        /** @var array<string, mixed> $validated */
        $this->assertDateRange($validated, 'created_from', 'created_to', 'created_at');

        return new PaymentListCriteria(
            query: $this->stringValue($validated, 'q'),
            status: $this->stringValue($validated, 'status'),
            invoiceId: $this->stringValue($validated, 'invoice_id'),
            providerKey: $this->stringValue($validated, 'provider_key'),
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
    private function initiateRules(): array
    {
        return [
            'invoice_id' => ['required', 'uuid'],
            'provider_key' => ['required', 'string', 'regex:/^[a-z0-9._-]+$/'],
            'amount' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3', 'alpha'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function listRules(int $maxLimit): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:'.implode(',', PaymentStatus::all())],
            'invoice_id' => ['nullable', 'uuid'],
            'provider_key' => ['nullable', 'string', 'regex:/^[a-z0-9._-]+$/'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.$maxLimit],
        ];
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
