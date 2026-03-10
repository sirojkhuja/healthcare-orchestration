<?php

namespace App\Modules\Treatment\Infrastructure\Persistence;

use App\Modules\Treatment\Application\Contracts\TreatmentItemRepository;
use App\Modules\Treatment\Application\Data\TreatmentItemData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseTreatmentItemRepository implements TreatmentItemRepository
{
    #[\Override]
    public function create(string $tenantId, string $planId, array $attributes): TreatmentItemData
    {
        $itemId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('treatment_plan_items')->insert([
            'id' => $itemId,
            'tenant_id' => $tenantId,
            'plan_id' => $planId,
            'item_type' => $attributes['item_type'],
            'title' => $attributes['title'],
            'description' => $attributes['description'],
            'instructions' => $attributes['instructions'],
            'sort_order' => $attributes['sort_order'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInPlan($tenantId, $planId, $itemId)
            ?? throw new \LogicException('Created treatment item could not be reloaded.');
    }

    #[\Override]
    public function delete(string $tenantId, string $planId, string $itemId): bool
    {
        return DB::table('treatment_plan_items')
            ->where('tenant_id', $tenantId)
            ->where('plan_id', $planId)
            ->where('id', $itemId)
            ->delete() > 0;
    }

    #[\Override]
    public function findInPlan(string $tenantId, string $planId, string $itemId): ?TreatmentItemData
    {
        $row = DB::table('treatment_plan_items')
            ->where('tenant_id', $tenantId)
            ->where('plan_id', $planId)
            ->where('id', $itemId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function listForPlan(string $tenantId, string $planId): array
    {
        /** @var list<stdClass> $rows */
        $rows = DB::table('treatment_plan_items')
            ->where('tenant_id', $tenantId)
            ->where('plan_id', $planId)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function update(string $tenantId, string $planId, string $itemId, array $updates): ?TreatmentItemData
    {
        if ($updates === []) {
            return $this->findInPlan($tenantId, $planId, $itemId);
        }

        DB::table('treatment_plan_items')
            ->where('tenant_id', $tenantId)
            ->where('plan_id', $planId)
            ->where('id', $itemId)
            ->update([
                ...$updates,
                'updated_at' => CarbonImmutable::now(),
            ]);

        return $this->findInPlan($tenantId, $planId, $itemId);
    }

    private function dateTime(mixed $value): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse($this->stringValue($value));
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function toData(stdClass $row): TreatmentItemData
    {
        return new TreatmentItemData(
            itemId: $this->stringValue($row->id ?? null),
            planId: $this->stringValue($row->plan_id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            itemType: $this->stringValue($row->item_type ?? null),
            title: $this->stringValue($row->title ?? null),
            description: $this->nullableString($row->description ?? null),
            instructions: $this->nullableString($row->instructions ?? null),
            sortOrder: $this->intValue($row->sort_order ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }
}
