<?php

namespace App\Modules\Billing\Infrastructure\Persistence;

use App\Modules\Billing\Application\Contracts\InvoiceRepository;
use App\Modules\Billing\Application\Data\InvoiceData;
use App\Modules\Billing\Application\Data\InvoiceItemData;
use App\Modules\Billing\Application\Data\InvoiceSearchCriteria;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseInvoiceRepository implements InvoiceRepository
{
    public function __construct(
        private readonly InvoiceRecordMapper $invoiceRecordMapper,
    ) {}

    #[\Override]
    public function allocateInvoiceNumber(string $tenantId): string
    {
        /** @var string $invoiceNumber */
        $invoiceNumber = DB::transaction(function () use ($tenantId): string {
            $now = CarbonImmutable::now();

            DB::table('invoice_number_sequences')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'current_value' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $record = DB::table('invoice_number_sequences')
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            $currentValue = $record instanceof stdClass && is_numeric($record->current_value ?? null)
                ? (int) $record->current_value
                : 0;
            $next = $currentValue + 1;

            DB::table('invoice_number_sequences')
                ->where('tenant_id', $tenantId)
                ->update([
                    'current_value' => $next,
                    'updated_at' => $now,
                ]);

            return sprintf('INV-%06d', $next);
        });

        return $invoiceNumber;
    }

    #[\Override]
    public function create(string $tenantId, array $attributes): InvoiceData
    {
        $invoiceId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $tenantId,
            'invoice_number' => $attributes['invoice_number'],
            'patient_id' => $attributes['patient_id'],
            'patient_display_name' => $attributes['patient_display_name'],
            'price_list_id' => $attributes['price_list_id'],
            'price_list_code' => $attributes['price_list_code'],
            'price_list_name' => $attributes['price_list_name'],
            'currency' => $attributes['currency'],
            'invoice_date' => $attributes['invoice_date'],
            'due_on' => $attributes['due_on'],
            'notes' => $attributes['notes'],
            'status' => $attributes['status'],
            'subtotal_amount' => $attributes['subtotal_amount'],
            'total_amount' => $attributes['total_amount'],
            'issued_at' => $attributes['issued_at'],
            'finalized_at' => $attributes['finalized_at'],
            'voided_at' => $attributes['voided_at'],
            'void_reason' => $attributes['void_reason'],
            'last_transition' => $this->jsonValue($attributes['last_transition']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $invoiceId)
            ?? throw new \LogicException('Created invoice could not be reloaded.');
    }

    #[\Override]
    public function createItem(string $tenantId, string $invoiceId, array $attributes): InvoiceItemData
    {
        $itemId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('invoice_items')->insert([
            'id' => $itemId,
            'tenant_id' => $tenantId,
            'invoice_id' => $invoiceId,
            'service_id' => $attributes['service_id'],
            'service_code' => $attributes['service_code'],
            'service_name' => $attributes['service_name'],
            'service_category' => $attributes['service_category'],
            'service_unit' => $attributes['service_unit'],
            'description' => $attributes['description'],
            'quantity' => $attributes['quantity'],
            'unit_price_amount' => $attributes['unit_price_amount'],
            'line_subtotal_amount' => $attributes['line_subtotal_amount'],
            'currency' => $attributes['currency'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findItem($tenantId, $invoiceId, $itemId)
            ?? throw new \LogicException('Created invoice item could not be reloaded.');
    }

    #[\Override]
    public function deleteItem(string $tenantId, string $invoiceId, string $itemId): bool
    {
        return DB::table('invoice_items')
            ->where('tenant_id', $tenantId)
            ->where('invoice_id', $invoiceId)
            ->where('id', $itemId)
            ->delete() > 0;
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $invoiceId, bool $withDeleted = false): ?InvoiceData
    {
        $query = $this->invoiceRowsQuery($tenantId)
            ->where('invoices.id', $invoiceId);

        if (! $withDeleted) {
            $query->whereNull('invoices.deleted_at');
        }

        $row = $query->first();

        if (! $row instanceof stdClass) {
            return null;
        }

        return $this->invoiceRecordMapper->toData($row, $this->listItems($tenantId, $invoiceId));
    }

    #[\Override]
    public function findItem(string $tenantId, string $invoiceId, string $itemId): ?InvoiceItemData
    {
        $row = DB::table('invoice_items')
            ->where('tenant_id', $tenantId)
            ->where('invoice_id', $invoiceId)
            ->where('id', $itemId)
            ->first();

        return $row instanceof stdClass ? $this->invoiceRecordMapper->toItemData($row) : null;
    }

    #[\Override]
    public function listItems(string $tenantId, string $invoiceId): array
    {
        /** @var list<stdClass> $rows */
        $rows = DB::table('invoice_items')
            ->where('tenant_id', $tenantId)
            ->where('invoice_id', $invoiceId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->all();

        return array_map($this->invoiceRecordMapper->toItemData(...), $rows);
    }

    #[\Override]
    public function refreshTotals(string $tenantId, string $invoiceId): ?InvoiceData
    {
        /** @var mixed $raw */
        $raw = DB::table('invoice_items')
            ->where('tenant_id', $tenantId)
            ->where('invoice_id', $invoiceId)
            ->selectRaw('COALESCE(SUM(line_subtotal_amount), 0) as subtotal')
            ->value('subtotal');
        $subtotal = $this->invoiceRecordMapper->decimalString($raw ?? 0);

        DB::table('invoices')
            ->where('tenant_id', $tenantId)
            ->where('id', $invoiceId)
            ->update([
                'subtotal_amount' => $subtotal,
                'total_amount' => $subtotal,
                'updated_at' => CarbonImmutable::now(),
            ]);

        return $this->findInTenant($tenantId, $invoiceId);
    }

    #[\Override]
    public function search(string $tenantId, InvoiceSearchCriteria $criteria): array
    {
        $query = $this->invoiceRowsQuery($tenantId)
            ->whereNull('invoices.deleted_at');

        foreach ([
            'status' => $criteria->status,
            'patient_id' => $criteria->patientId,
        ] as $column => $value) {
            if ($value !== null) {
                $query->where('invoices.'.$column, $value);
            }
        }

        if ($criteria->issuedFrom !== null) {
            $query->where('invoices.issued_at', '>=', CarbonImmutable::parse($criteria->issuedFrom)->startOfDay());
        }

        if ($criteria->issuedTo !== null) {
            $query->where('invoices.issued_at', '<=', CarbonImmutable::parse($criteria->issuedTo)->endOfDay());
        }

        if ($criteria->dueFrom !== null) {
            $query->where('invoices.due_on', '>=', $criteria->dueFrom);
        }

        if ($criteria->dueTo !== null) {
            $query->where('invoices.due_on', '<=', $criteria->dueTo);
        }

        if ($criteria->createdFrom !== null) {
            $query->where('invoices.created_at', '>=', CarbonImmutable::parse($criteria->createdFrom)->startOfDay());
        }

        if ($criteria->createdTo !== null) {
            $query->where('invoices.created_at', '<=', CarbonImmutable::parse($criteria->createdTo)->endOfDay());
        }

        if ($criteria->query !== null && trim($criteria->query) !== '') {
            $pattern = '%'.mb_strtolower(trim($criteria->query)).'%';
            $query->where(function (Builder $builder) use ($pattern): void {
                $builder
                    ->whereRaw('LOWER(invoices.invoice_number) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(invoices.patient_display_name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(invoices.notes, \'\')) LIKE ?', [$pattern]);
            });
        }

        /** @var list<stdClass> $rows */
        $rows = $query
            ->orderByRaw('COALESCE(invoices.finalized_at, invoices.issued_at, invoices.created_at) DESC')
            ->orderByDesc('invoices.invoice_number')
            ->limit($criteria->limit)
            ->get()
            ->all();

        return array_map(fn (stdClass $row): InvoiceData => $this->invoiceRecordMapper->toData($row), $rows);
    }

    #[\Override]
    public function softDelete(string $tenantId, string $invoiceId, CarbonImmutable $deletedAt): bool
    {
        return DB::table('invoices')
            ->where('tenant_id', $tenantId)
            ->where('id', $invoiceId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
            ]) > 0;
    }

    #[\Override]
    public function update(string $tenantId, string $invoiceId, array $updates): ?InvoiceData
    {
        if ($updates === []) {
            return $this->findInTenant($tenantId, $invoiceId);
        }

        unset($updates['invoice_id'], $updates['tenant_id']);

        if (array_key_exists('last_transition', $updates)) {
            $updates['last_transition'] = $this->jsonValue($updates['last_transition']);
        }

        DB::table('invoices')
            ->where('tenant_id', $tenantId)
            ->where('id', $invoiceId)
            ->whereNull('deleted_at')
            ->update([
                ...$updates,
                'updated_at' => CarbonImmutable::now(),
            ]);

        return $this->findInTenant($tenantId, $invoiceId);
    }

    #[\Override]
    public function updateItem(string $tenantId, string $invoiceId, string $itemId, array $updates): ?InvoiceItemData
    {
        if ($updates === []) {
            return $this->findItem($tenantId, $invoiceId, $itemId);
        }

        DB::table('invoice_items')
            ->where('tenant_id', $tenantId)
            ->where('invoice_id', $invoiceId)
            ->where('id', $itemId)
            ->update([
                ...$updates,
                'updated_at' => CarbonImmutable::now(),
            ]);

        return $this->findItem($tenantId, $invoiceId, $itemId);
    }

    private function invoiceRowsQuery(string $tenantId): Builder
    {
        $itemCounts = DB::table('invoice_items')
            ->selectRaw('invoice_id, COUNT(*) as item_count')
            ->where('tenant_id', $tenantId)
            ->groupBy('invoice_id');

        return DB::table('invoices')
            ->leftJoinSub($itemCounts, 'item_counts', function (JoinClause $join): void {
                $join->on('item_counts.invoice_id', '=', 'invoices.id');
            })
            ->where('invoices.tenant_id', $tenantId)
            ->select([
                'invoices.id as invoice_id',
                'invoices.tenant_id',
                'invoices.invoice_number',
                'invoices.patient_id',
                'invoices.patient_display_name',
                'invoices.price_list_id',
                'invoices.price_list_code',
                'invoices.price_list_name',
                'invoices.currency',
                'invoices.invoice_date',
                'invoices.due_on',
                'invoices.notes',
                'invoices.status',
                'invoices.subtotal_amount',
                'invoices.total_amount',
                'invoices.issued_at',
                'invoices.finalized_at',
                'invoices.voided_at',
                'invoices.void_reason',
                'invoices.last_transition',
                'invoices.deleted_at',
                'invoices.created_at',
                'invoices.updated_at',
                DB::raw('COALESCE(item_counts.item_count, 0) as item_count'),
            ]);
    }

    private function jsonValue(mixed $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR);
    }
}
