<?php

namespace App\Modules\Billing\Infrastructure\Persistence;

use App\Modules\Billing\Application\Contracts\PaymentRepository;
use App\Modules\Billing\Application\Data\PaymentData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabasePaymentRepository implements PaymentRepository
{
    public function __construct(
        private readonly PaymentRecordMapper $paymentRecordMapper,
    ) {}

    #[\Override]
    public function create(string $tenantId, array $attributes): PaymentData
    {
        $paymentId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('payments')->insert([
            'id' => $paymentId,
            'tenant_id' => $tenantId,
            'invoice_id' => $attributes['invoice_id'],
            'invoice_number' => $attributes['invoice_number'],
            'provider_key' => $attributes['provider_key'],
            'amount' => $attributes['amount'],
            'currency' => $attributes['currency'],
            'description' => $attributes['description'],
            'status' => $attributes['status'],
            'provider_payment_id' => $attributes['provider_payment_id'],
            'provider_status' => $attributes['provider_status'],
            'checkout_url' => $attributes['checkout_url'],
            'failure_code' => $attributes['failure_code'],
            'failure_message' => $attributes['failure_message'],
            'cancel_reason' => $attributes['cancel_reason'],
            'refund_reason' => $attributes['refund_reason'],
            'last_transition' => $this->jsonValue($attributes['last_transition']),
            'initiated_at' => $attributes['initiated_at'],
            'pending_at' => $attributes['pending_at'],
            'captured_at' => $attributes['captured_at'],
            'failed_at' => $attributes['failed_at'],
            'canceled_at' => $attributes['canceled_at'],
            'refunded_at' => $attributes['refunded_at'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $paymentId)
            ?? throw new \LogicException('Created payment could not be reloaded.');
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $paymentId): ?PaymentData
    {
        $row = DB::table('payments')
            ->where('tenant_id', $tenantId)
            ->where('id', $paymentId)
            ->first();

        return $row instanceof stdClass ? $this->paymentRecordMapper->toData($row) : null;
    }

    #[\Override]
    public function update(string $tenantId, string $paymentId, array $updates): ?PaymentData
    {
        if ($updates === []) {
            return $this->findInTenant($tenantId, $paymentId);
        }

        unset($updates['payment_id'], $updates['tenant_id']);

        if (array_key_exists('last_transition', $updates)) {
            $updates['last_transition'] = $this->jsonValue($updates['last_transition']);
        }

        DB::table('payments')
            ->where('tenant_id', $tenantId)
            ->where('id', $paymentId)
            ->update([
                ...$updates,
                'updated_at' => CarbonImmutable::now(),
            ]);

        return $this->findInTenant($tenantId, $paymentId);
    }

    private function jsonValue(mixed $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR);
    }
}
