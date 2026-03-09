<?php

namespace App\Modules\Provider\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Provider\Application\Contracts\ProviderRepository;
use App\Modules\Provider\Application\Data\ProviderData;
use App\Modules\Provider\Application\Data\ProviderTimeOffData;
use App\Modules\Provider\Application\Data\ProviderWorkHoursData;
use App\Modules\Scheduling\Application\Contracts\AvailabilityCacheInvalidator;
use App\Modules\Scheduling\Application\Contracts\AvailabilityRuleRepository;
use App\Modules\Scheduling\Application\Data\AvailabilityRuleData;
use App\Modules\Scheduling\Domain\Availability\AvailabilityScopeType;
use App\Modules\Scheduling\Domain\Availability\AvailabilityType;
use App\Modules\Scheduling\Domain\Availability\AvailabilityWeekday;
use App\Modules\TenantManagement\Application\Contracts\ClinicRepository;
use App\Modules\TenantManagement\Application\Contracts\TenantConfigurationRepository;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ProviderScheduleService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ProviderRepository $providerRepository,
        private readonly ClinicRepository $clinicRepository,
        private readonly TenantConfigurationRepository $tenantConfigurationRepository,
        private readonly AvailabilityRuleRepository $availabilityRuleRepository,
        private readonly AvailabilityCacheInvalidator $availabilityCacheInvalidator,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createTimeOff(string $providerId, array $attributes): ProviderTimeOffData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $provider = $this->providerOrFail($tenantId, $providerId);
        $normalized = $this->normalizedTimeOffAttributes($attributes);
        $this->assertNoTimeOffConflict($tenantId, $provider->providerId, $normalized);

        $rule = $this->availabilityRuleRepository->create($tenantId, $provider->providerId, $normalized);
        $timeOff = $this->timeOffFromRule($rule);
        $this->availabilityCacheInvalidator->invalidate($tenantId);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'providers.time_off_created',
            objectType: 'provider_time_off',
            objectId: $timeOff->timeOffId,
            after: $timeOff->toArray(),
            metadata: ['provider_id' => $provider->providerId],
        ));

        return $timeOff;
    }

    public function deleteTimeOff(string $providerId, string $timeOffId): ProviderTimeOffData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $provider = $this->providerOrFail($tenantId, $providerId);
        $rule = $this->timeOffRuleOrFail($tenantId, $provider->providerId, $timeOffId);
        $timeOff = $this->timeOffFromRule($rule);

        if (! $this->availabilityRuleRepository->delete($tenantId, $provider->providerId, $rule->ruleId)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->availabilityCacheInvalidator->invalidate($tenantId);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'providers.time_off_deleted',
            objectType: 'provider_time_off',
            objectId: $timeOff->timeOffId,
            before: $timeOff->toArray(),
            metadata: ['provider_id' => $provider->providerId],
        ));

        return $timeOff;
    }

    public function getWorkHours(string $providerId): ProviderWorkHoursData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $provider = $this->providerOrFail($tenantId, $providerId);

        return $this->workHoursData(
            tenantId: $tenantId,
            provider: $provider,
            rules: $this->availabilityRuleRepository->listForProvider($tenantId, $provider->providerId),
        );
    }

    /**
     * @return list<ProviderTimeOffData>
     */
    public function listTimeOff(string $providerId): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $provider = $this->providerOrFail($tenantId, $providerId);

        return array_map(
            $this->timeOffFromRule(...),
            $this->timeOffRules($this->availabilityRuleRepository->listForProvider($tenantId, $provider->providerId)),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateTimeOff(string $providerId, string $timeOffId, array $attributes): ProviderTimeOffData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $provider = $this->providerOrFail($tenantId, $providerId);
        $rule = $this->timeOffRuleOrFail($tenantId, $provider->providerId, $timeOffId);
        $current = $this->timeOffFromRule($rule);
        $candidate = $this->mergedTimeOffAttributes($rule, $attributes);
        $updates = $this->changedTimeOffAttributes($rule, $candidate);

        if ($updates === []) {
            return $current;
        }

        $this->assertNoTimeOffConflict($tenantId, $provider->providerId, $candidate, $rule->ruleId);
        $updated = $this->availabilityRuleRepository->update($tenantId, $provider->providerId, $rule->ruleId, $updates);

        if (! $updated instanceof AvailabilityRuleData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $timeOff = $this->timeOffFromRule($updated);
        $this->availabilityCacheInvalidator->invalidate($tenantId);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'providers.time_off_updated',
            objectType: 'provider_time_off',
            objectId: $timeOff->timeOffId,
            before: $current->toArray(),
            after: $timeOff->toArray(),
            metadata: ['provider_id' => $provider->providerId],
        ));

        return $timeOff;
    }

    public function updateWorkHours(string $providerId, ProviderWorkHoursData $workHours): ProviderWorkHoursData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $provider = $this->providerOrFail($tenantId, $providerId);
        $existingRules = $this->availabilityRuleRepository->listForProvider($tenantId, $provider->providerId);
        $before = $this->workHoursData($tenantId, $provider, $existingRules);
        $normalizedDays = $this->normalizedDays($workHours->days);

        if ($before->days === $normalizedDays) {
            return $before;
        }

        $this->availabilityRuleRepository->replaceWeeklyRules($tenantId, $provider->providerId, $normalizedDays);
        $after = $this->getWorkHours($provider->providerId);
        $this->availabilityCacheInvalidator->invalidate($tenantId);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'providers.work_hours_updated',
            objectType: 'provider',
            objectId: $provider->providerId,
            before: $before->toArray(),
            after: $after->toArray(),
        ));

        return $after;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *      scope_type: string,
     *      availability_type: string,
     *      weekday: string|null,
     *      specific_date: CarbonImmutable|null,
     *      start_time: string,
     *      end_time: string,
     *      notes: string|null
     *  }
     */
    private function mergedTimeOffAttributes(AvailabilityRuleData $rule, array $attributes): array
    {
        return $this->normalizedTimeOffAttributes([
            'specific_date' => $attributes['specific_date'] ?? $rule->specificDate?->toDateString(),
            'start_time' => $attributes['start_time'] ?? $rule->startTime,
            'end_time' => $attributes['end_time'] ?? $rule->endTime,
            'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : $rule->notes,
        ]);
    }

    /**
     * @param  array{
     *      scope_type: string,
     *      availability_type: string,
     *      weekday: string|null,
     *      specific_date: CarbonImmutable|null,
     *      start_time: string,
     *      end_time: string,
     *      notes: string|null
     *  }  $candidate
     * @return array{
     *      specific_date?: CarbonImmutable,
     *      start_time?: string,
     *      end_time?: string,
     *      notes?: string|null
     * }
     */
    private function changedTimeOffAttributes(AvailabilityRuleData $rule, array $candidate): array
    {
        $updates = [];

        if ($candidate['specific_date']?->toDateString() !== $rule->specificDate?->toDateString()) {
            $updates['specific_date'] = $candidate['specific_date']
                ?? throw new UnprocessableEntityHttpException('Time-off records require a specific date.');
        }

        if ($candidate['start_time'] !== $rule->startTime) {
            $updates['start_time'] = $candidate['start_time'];
        }

        if ($candidate['end_time'] !== $rule->endTime) {
            $updates['end_time'] = $candidate['end_time'];
        }

        if ($candidate['notes'] !== $rule->notes) {
            $updates['notes'] = $candidate['notes'];
        }

        return $updates;
    }

    /**
     * @param  array{
     *      scope_type: string,
     *      availability_type: string,
     *      weekday: string|null,
     *      specific_date: CarbonImmutable|null,
     *      start_time: string,
     *      end_time: string,
     *      notes: string|null
     *  }  $attributes
     */
    private function assertNoTimeOffConflict(
        string $tenantId,
        string $providerId,
        array $attributes,
        ?string $exceptRuleId = null,
    ): void {
        if ($this->availabilityRuleRepository->hasConflict(
            tenantId: $tenantId,
            providerId: $providerId,
            scopeType: $attributes['scope_type'],
            availabilityType: $attributes['availability_type'],
            weekday: $attributes['weekday'],
            specificDate: $attributes['specific_date'],
            startTime: $attributes['start_time'],
            endTime: $attributes['end_time'],
            exceptRuleId: $exceptRuleId,
        )) {
            throw new ConflictHttpException('The time-off interval overlaps an existing time-off interval.');
        }
    }

    /**
     * @param  list<AvailabilityRuleData>  $rules
     * @return list<array{start_time: string, end_time: string}>
     */
    private function dayIntervalsFromRules(string $weekday, array $rules): array
    {
        $available = [];
        $unavailable = [];

        foreach ($rules as $rule) {
            if ($rule->weekday !== $weekday) {
                continue;
            }

            $interval = [
                'start' => $this->timeToMinutes($rule->startTime),
                'end' => $this->timeToMinutes($rule->endTime),
            ];

            if ($rule->availabilityType === AvailabilityType::AVAILABLE) {
                $available[] = $interval;
            } else {
                $unavailable[] = $interval;
            }
        }

        $effective = $this->mergeIntervals($available);
        $effective = $this->subtractIntervals($effective, $this->mergeIntervals($unavailable));

        return array_map(fn (array $interval): array => [
            'start_time' => $this->minutesToTime($interval['start']),
            'end_time' => $this->minutesToTime($interval['end']),
        ], $effective);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *      scope_type: string,
     *      availability_type: string,
     *      weekday: string|null,
     *      specific_date: CarbonImmutable|null,
     *      start_time: string,
     *      end_time: string,
     *      notes: string|null
     *  }
     */
    private function normalizedTimeOffAttributes(array $attributes): array
    {
        $startTime = $this->requiredString($attributes['start_time'] ?? null, 'Time-off start_time is required.');
        $endTime = $this->requiredString($attributes['end_time'] ?? null, 'Time-off end_time is required.');

        if (! $this->isTime($startTime) || ! $this->isTime($endTime)) {
            throw new UnprocessableEntityHttpException('Time-off intervals must use HH:MM 24-hour times.');
        }

        if ($startTime >= $endTime) {
            throw new UnprocessableEntityHttpException('Time-off end_time must be later than start_time.');
        }

        return [
            'scope_type' => AvailabilityScopeType::DATE,
            'availability_type' => AvailabilityType::UNAVAILABLE,
            'weekday' => null,
            'specific_date' => $this->requiredDate($attributes['specific_date'] ?? null),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'notes' => $this->nullableString($attributes['notes'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $days
     * @return array<string, list<array{start_time: string, end_time: string}>>
     */
    private function normalizedDays(array $days): array
    {
        $normalized = array_fill_keys(AvailabilityWeekday::all(), []);

        foreach ($days as $day => $intervals) {
            if (! in_array($day, AvailabilityWeekday::all(), true)) {
                throw new UnprocessableEntityHttpException('Provider work-hours days must use monday through sunday keys.');
            }

            if (! is_array($intervals)) {
                throw new UnprocessableEntityHttpException("Provider work-hours for {$day} must be a list of intervals.");
            }

            $normalized[$day] = $this->normalizedDayIntervals($day, array_values($intervals));
        }

        return $normalized;
    }

    /**
     * @param  list<mixed>  $intervals
     * @return list<array{start_time: string, end_time: string}>
     */
    private function normalizedDayIntervals(string $day, array $intervals): array
    {
        $normalized = [];

        foreach ($intervals as $interval) {
            if (! is_array($interval)) {
                throw new UnprocessableEntityHttpException("Each {$day} interval must be an object.");
            }

            $start = $this->requiredString($interval['start_time'] ?? null, "Each {$day} interval requires start_time.");
            $end = $this->requiredString($interval['end_time'] ?? null, "Each {$day} interval requires end_time.");

            if (! $this->isTime($start) || ! $this->isTime($end)) {
                throw new UnprocessableEntityHttpException("Each {$day} interval must use HH:MM 24-hour times.");
            }

            if ($start >= $end) {
                throw new UnprocessableEntityHttpException("Each {$day} interval must end after it starts.");
            }

            $normalized[] = [
                'start_time' => $start,
                'end_time' => $end,
            ];
        }

        usort(
            $normalized,
            static fn (array $left, array $right): int => [$left['start_time'], $left['end_time']] <=> [$right['start_time'], $right['end_time']],
        );

        $lastEnd = null;

        foreach ($normalized as $interval) {
            if ($lastEnd !== null && $interval['start_time'] < $lastEnd) {
                throw new UnprocessableEntityHttpException("Intervals for {$day} must not overlap.");
            }

            $lastEnd = $interval['end_time'];
        }

        return $this->mergeCanonicalDayIntervals($normalized);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new UnprocessableEntityHttpException('The provided value must be a string.');
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function providerOrFail(string $tenantId, string $providerId): ProviderData
    {
        $provider = $this->providerRepository->findInTenant($tenantId, $providerId);

        if (! $provider instanceof ProviderData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $provider;
    }

    private function requiredDate(mixed $value): CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            throw new UnprocessableEntityHttpException('Time-off specific_date is required.');
        }

        $date = CarbonImmutable::createFromFormat('Y-m-d', trim($value), 'UTC');

        if (! $date instanceof CarbonImmutable) {
            throw new UnprocessableEntityHttpException('Time-off specific_date must use Y-m-d format.');
        }

        return $date->startOfDay();
    }

    private function requiredString(mixed $value, string $message): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new UnprocessableEntityHttpException($message);
        }

        return trim($value);
    }

    private function resolveTimezone(string $tenantId, ProviderData $provider): string
    {
        if ($provider->clinicId !== null) {
            $clinic = $this->clinicRepository->findClinic($tenantId, $provider->clinicId);

            if ($clinic !== null) {
                $timezone = $this->clinicRepository->settings($tenantId, $clinic->clinicId)->timezone;

                if (is_string($timezone) && trim($timezone) !== '') {
                    return trim($timezone);
                }
            }
        }

        $tenantTimezone = $this->tenantConfigurationRepository->settings($tenantId)->timezone;

        if (is_string($tenantTimezone) && trim($tenantTimezone) !== '') {
            return trim($tenantTimezone);
        }

        /** @var mixed $applicationTimezone */
        $applicationTimezone = config('app.timezone');

        return is_string($applicationTimezone) && trim($applicationTimezone) !== ''
            ? trim($applicationTimezone)
            : 'UTC';
    }

    /**
     * @param  list<array{start_time: string, end_time: string}>  $intervals
     * @return list<array{start_time: string, end_time: string}>
     */
    private function mergeCanonicalDayIntervals(array $intervals): array
    {
        if ($intervals === []) {
            return [];
        }

        $current = array_shift($intervals);
        $merged = [];

        foreach ($intervals as $interval) {
            if ($interval['start_time'] <= $current['end_time']) {
                $current['end_time'] = max($current['end_time'], $interval['end_time']);

                continue;
            }

            $merged[] = $current;
            $current = $interval;
        }

        $merged[] = $current;

        return $merged;
    }

    private function isTime(string $value): bool
    {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
    }

    /**
     * @param  list<array{start: int, end: int}>  $intervals
     * @return list<array{start: int, end: int}>
     */
    private function mergeIntervals(array $intervals): array
    {
        if ($intervals === []) {
            return [];
        }

        usort($intervals, static fn (array $left, array $right): int => [$left['start'], $left['end']] <=> [$right['start'], $right['end']]);
        $current = array_shift($intervals);
        $merged = [];

        foreach ($intervals as $interval) {
            if ($interval['start'] <= $current['end']) {
                $current['end'] = max($current['end'], $interval['end']);

                continue;
            }

            $merged[] = $current;
            $current = $interval;
        }

        $merged[] = $current;

        return $merged;
    }

    private function minutesToTime(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return sprintf('%02d:%02d', $hours, $mins);
    }

    /**
     * @param  list<array{start: int, end: int}>  $base
     * @param  list<array{start: int, end: int}>  $subtract
     * @return list<array{start: int, end: int}>
     */
    private function subtractIntervals(array $base, array $subtract): array
    {
        $result = [];

        foreach ($base as $interval) {
            $segments = [$interval];

            foreach ($subtract as $cut) {
                $nextSegments = [];

                foreach ($segments as $segment) {
                    if ($cut['end'] <= $segment['start'] || $cut['start'] >= $segment['end']) {
                        $nextSegments[] = $segment;

                        continue;
                    }

                    if ($cut['start'] > $segment['start']) {
                        $nextSegments[] = [
                            'start' => $segment['start'],
                            'end' => $cut['start'],
                        ];
                    }

                    if ($cut['end'] < $segment['end']) {
                        $nextSegments[] = [
                            'start' => $cut['end'],
                            'end' => $segment['end'],
                        ];
                    }
                }

                $segments = $nextSegments;

                if ($segments === []) {
                    break;
                }
            }

            foreach ($segments as $segment) {
                if ($segment['start'] < $segment['end']) {
                    $result[] = $segment;
                }
            }
        }

        return $result;
    }

    private function timeOffFromRule(AvailabilityRuleData $rule): ProviderTimeOffData
    {
        return new ProviderTimeOffData(
            timeOffId: $rule->ruleId,
            providerId: $rule->providerId,
            specificDate: $rule->specificDate
                ?? throw new UnprocessableEntityHttpException('Time-off rules require a specific date.'),
            startTime: $rule->startTime,
            endTime: $rule->endTime,
            notes: $rule->notes,
            createdAt: $rule->createdAt,
            updatedAt: $rule->updatedAt,
        );
    }

    private function timeToMinutes(string $value): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $value));

        return ($hours * 60) + $minutes;
    }

    /**
     * @param  list<AvailabilityRuleData>  $rules
     * @return list<AvailabilityRuleData>
     */
    private function timeOffRules(array $rules): array
    {
        return array_values(array_filter(
            $rules,
            static fn (AvailabilityRuleData $rule): bool => $rule->scopeType === AvailabilityScopeType::DATE
                && $rule->availabilityType === AvailabilityType::UNAVAILABLE,
        ));
    }

    private function timeOffRuleOrFail(string $tenantId, string $providerId, string $timeOffId): AvailabilityRuleData
    {
        $rule = $this->availabilityRuleRepository->findForProvider($tenantId, $providerId, $timeOffId);

        if (
            ! $rule instanceof AvailabilityRuleData
            || $rule->scopeType !== AvailabilityScopeType::DATE
            || $rule->availabilityType !== AvailabilityType::UNAVAILABLE
        ) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $rule;
    }

    /**
     * @param  list<AvailabilityRuleData>  $rules
     */
    private function workHoursData(string $tenantId, ProviderData $provider, array $rules): ProviderWorkHoursData
    {
        $weeklyRules = array_values(array_filter(
            $rules,
            static fn (AvailabilityRuleData $rule): bool => $rule->scopeType === AvailabilityScopeType::WEEKLY,
        ));
        $days = [];
        $updatedAt = null;

        foreach (AvailabilityWeekday::all() as $weekday) {
            $days[$weekday] = $this->dayIntervalsFromRules($weekday, $weeklyRules);
        }

        foreach ($weeklyRules as $rule) {
            if ($updatedAt === null || $rule->updatedAt->greaterThan($updatedAt)) {
                $updatedAt = $rule->updatedAt;
            }
        }

        return new ProviderWorkHoursData(
            providerId: $provider->providerId,
            days: $days,
            timezone: $this->resolveTimezone($tenantId, $provider),
            updatedAt: $updatedAt,
        );
    }
}
