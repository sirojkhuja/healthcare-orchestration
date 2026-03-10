<?php

namespace App\Modules\Scheduling\Infrastructure\Persistence;

use App\Modules\Scheduling\Application\Contracts\AvailabilityRuleRepository;
use App\Modules\Scheduling\Application\Data\AvailabilityRuleData;
use App\Modules\Scheduling\Domain\Availability\AvailabilityScopeType;
use App\Modules\Scheduling\Domain\Availability\AvailabilityType;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use stdClass;

final class DatabaseAvailabilityRuleRepository implements AvailabilityRuleRepository
{
    #[\Override]
    public function create(string $tenantId, string $providerId, array $attributes): AvailabilityRuleData
    {
        $ruleId = (string) Str::uuid();
        $timestamp = CarbonImmutable::now();

        DB::table('provider_availability_rules')->insert([
            'id' => $ruleId,
            'tenant_id' => $tenantId,
            'provider_id' => $providerId,
            'scope_type' => $attributes['scope_type'],
            'availability_type' => $attributes['availability_type'],
            'weekday' => $attributes['weekday'],
            'specific_date' => $attributes['specific_date']?->toDateString(),
            'start_time' => $attributes['start_time'],
            'end_time' => $attributes['end_time'],
            'notes' => $attributes['notes'],
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return $this->findForProvider($tenantId, $providerId, $ruleId)
            ?? throw new LogicException('The created availability rule could not be reloaded.');
    }

    #[\Override]
    public function delete(string $tenantId, string $providerId, string $ruleId): bool
    {
        return DB::table('provider_availability_rules')
            ->where('tenant_id', $tenantId)
            ->where('provider_id', $providerId)
            ->where('id', $ruleId)
            ->delete() > 0;
    }

    #[\Override]
    public function findForProvider(string $tenantId, string $providerId, string $ruleId): ?AvailabilityRuleData
    {
        $row = $this->baseQuery()
            ->where('tenant_id', $tenantId)
            ->where('provider_id', $providerId)
            ->where('id', $ruleId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function replaceWeeklyRules(string $tenantId, string $providerId, array $days): array
    {
        DB::transaction(function () use ($tenantId, $providerId, $days): void {
            DB::table('provider_availability_rules')
                ->where('tenant_id', $tenantId)
                ->where('provider_id', $providerId)
                ->where('scope_type', AvailabilityScopeType::WEEKLY)
                ->delete();

            foreach ($days as $weekday => $intervals) {
                foreach ($intervals as $interval) {
                    $timestamp = CarbonImmutable::now();

                    DB::table('provider_availability_rules')->insert([
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'provider_id' => $providerId,
                        'scope_type' => AvailabilityScopeType::WEEKLY,
                        'availability_type' => AvailabilityType::AVAILABLE,
                        'weekday' => $weekday,
                        'specific_date' => null,
                        'start_time' => $interval['start_time'],
                        'end_time' => $interval['end_time'],
                        'notes' => null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                }
            }
        });

        return array_values(array_filter(
            $this->listForProvider($tenantId, $providerId),
            static fn (AvailabilityRuleData $rule): bool => $rule->scopeType === AvailabilityScopeType::WEEKLY,
        ));
    }

    #[\Override]
    public function hasConflict(
        string $tenantId,
        string $providerId,
        string $scopeType,
        string $availabilityType,
        ?string $weekday,
        ?CarbonImmutable $specificDate,
        string $startTime,
        string $endTime,
        ?string $exceptRuleId = null,
    ): bool {
        $query = DB::table('provider_availability_rules')
            ->where('tenant_id', $tenantId)
            ->where('provider_id', $providerId)
            ->where('scope_type', $scopeType)
            ->where('availability_type', $availabilityType)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);

        if ($scopeType === AvailabilityScopeType::WEEKLY) {
            $query->where('weekday', $weekday);
        } else {
            $query->whereDate('specific_date', $specificDate?->toDateString() ?? '');
        }

        if (is_string($exceptRuleId) && $exceptRuleId !== '') {
            $query->where('id', '!=', $exceptRuleId);
        }

        return $query->exists();
    }

    #[\Override]
    public function listForProvider(string $tenantId, string $providerId): array
    {
        /** @var list<stdClass> $rows */
        $rows = $this->orderedQuery()
            ->where('tenant_id', $tenantId)
            ->where('provider_id', $providerId)
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function listRelevantForDateRange(
        string $tenantId,
        string $providerId,
        CarbonImmutable $dateFrom,
        CarbonImmutable $dateTo,
    ): array {
        /** @var list<stdClass> $rows */
        $rows = $this->orderedQuery()
            ->where('tenant_id', $tenantId)
            ->where('provider_id', $providerId)
            ->where(function (Builder $query) use ($dateFrom, $dateTo): void {
                $query->where('scope_type', AvailabilityScopeType::WEEKLY)
                    ->orWhere(function (Builder $nested) use ($dateFrom, $dateTo): void {
                        $nested->where('scope_type', AvailabilityScopeType::DATE)
                            ->whereDate('specific_date', '>=', $dateFrom->toDateString())
                            ->whereDate('specific_date', '<=', $dateTo->toDateString());
                    });
            })
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function update(string $tenantId, string $providerId, string $ruleId, array $attributes): ?AvailabilityRuleData
    {
        if ($attributes === []) {
            return $this->findForProvider($tenantId, $providerId, $ruleId);
        }

        $payload = $attributes;

        if (array_key_exists('specific_date', $payload)) {
            $payload['specific_date'] = $payload['specific_date']?->toDateString();
        }

        DB::table('provider_availability_rules')
            ->where('tenant_id', $tenantId)
            ->where('provider_id', $providerId)
            ->where('id', $ruleId)
            ->update($payload + ['updated_at' => CarbonImmutable::now()]);

        return $this->findForProvider($tenantId, $providerId, $ruleId);
    }

    private function baseQuery(): Builder
    {
        return DB::table('provider_availability_rules')->select([
            'id',
            'tenant_id',
            'provider_id',
            'scope_type',
            'availability_type',
            'weekday',
            'specific_date',
            'start_time',
            'end_time',
            'notes',
            'created_at',
            'updated_at',
        ]);
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

    private function nullableDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        $date = CarbonImmutable::createFromFormat('Y-m-d', $this->stringValue($value), 'UTC');

        return $date instanceof CarbonImmutable ? $date->startOfDay() : null;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function orderedQuery(): Builder
    {
        return $this->baseQuery()
            ->orderBy('scope_type')
            ->orderBy('weekday')
            ->orderBy('specific_date')
            ->orderBy('start_time')
            ->orderBy('created_at');
    }

    private function toData(stdClass $row): AvailabilityRuleData
    {
        return new AvailabilityRuleData(
            ruleId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            providerId: $this->stringValue($row->provider_id ?? null),
            scopeType: $this->stringValue($row->scope_type ?? null),
            availabilityType: $this->stringValue($row->availability_type ?? null),
            weekday: $this->nullableString($row->weekday ?? null),
            specificDate: $this->nullableDate($row->specific_date ?? null),
            startTime: $this->stringValue($row->start_time ?? null),
            endTime: $this->stringValue($row->end_time ?? null),
            notes: $this->nullableString($row->notes ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
