<?php

namespace App\Modules\Billing\Infrastructure\Persistence;

use App\Modules\Billing\Application\Contracts\PaymentRepository;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Data\PaymentListCriteria;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
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
    public function find(string $paymentId): ?PaymentData
    {
        $row = DB::table('payments')
            ->where('id', $paymentId)
            ->first();

        return $row instanceof stdClass ? $this->paymentRecordMapper->toData($row) : null;
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
    public function findByProviderPaymentId(string $providerKey, string $providerPaymentId): ?PaymentData
    {
        $row = DB::table('payments')
            ->where('provider_key', $providerKey)
            ->where('provider_payment_id', $providerPaymentId)
            ->orderByDesc('updated_at')
            ->first();

        return $row instanceof stdClass ? $this->paymentRecordMapper->toData($row) : null;
    }

    #[\Override]
    public function search(string $tenantId, PaymentListCriteria $criteria): array
    {
        $query = DB::table('payments')
            ->where('tenant_id', $tenantId);

        foreach ([
            'status' => $criteria->status,
            'invoice_id' => $criteria->invoiceId,
            'provider_key' => $criteria->providerKey,
        ] as $column => $value) {
            if ($value !== null) {
                $query->where($column, $value);
            }
        }

        if ($criteria->createdFrom !== null) {
            $query->where('created_at', '>=', CarbonImmutable::parse($criteria->createdFrom)->startOfDay());
        }

        if ($criteria->createdTo !== null) {
            $query->where('created_at', '<=', CarbonImmutable::parse($criteria->createdTo)->endOfDay());
        }

        if ($criteria->query !== null && trim($criteria->query) !== '') {
            $pattern = '%'.mb_strtolower(trim($criteria->query)).'%';
            $query->where(function (Builder $builder) use ($pattern): void {
                $builder
                    ->whereRaw('LOWER(invoice_number) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(provider_key) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(provider_payment_id, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$pattern]);
            });
        }

        /** @var list<stdClass> $rows */
        $rows = $query
            ->orderByRaw('COALESCE(refunded_at, captured_at, failed_at, canceled_at, pending_at, initiated_at, created_at) DESC')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($criteria->limit)
            ->get()
            ->all();

        return array_map(
            fn (stdClass $row): PaymentData => $this->paymentRecordMapper->toData($row),
            $rows,
        );
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
