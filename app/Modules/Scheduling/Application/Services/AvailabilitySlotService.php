<?php

namespace App\Modules\Scheduling\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Provider\Application\Contracts\ProviderRepository;
use App\Modules\Provider\Application\Data\ProviderData;
use App\Modules\Scheduling\Application\Contracts\AppointmentRepository;
use App\Modules\Scheduling\Application\Contracts\AvailabilityCacheInvalidator;
use App\Modules\Scheduling\Application\Contracts\AvailabilityRuleRepository;
use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Application\Data\AvailabilityRuleData;
use App\Modules\Scheduling\Application\Data\AvailabilitySlotData;
use App\Modules\Scheduling\Application\Data\AvailabilitySlotResultData;
use App\Modules\Scheduling\Domain\Availability\AvailabilityType;
use App\Modules\Scheduling\Domain\Availability\AvailabilityWeekday;
use App\Modules\TenantManagement\Application\Contracts\ClinicRepository;
use App\Modules\TenantManagement\Application\Contracts\TenantConfigurationRepository;
use App\Modules\TenantManagement\Application\Data\ClinicHolidayData;
use App\Modules\TenantManagement\Application\Data\ClinicSettingsData;
use App\Modules\TenantManagement\Application\Data\ClinicWorkHoursData;
use App\Modules\TenantManagement\Domain\Clinics\ClinicStatus;
use App\Shared\Application\Contracts\TenantCache;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class AvailabilitySlotService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantCache $tenantCache,
        private readonly ProviderRepository $providerRepository,
        private readonly ClinicRepository $clinicRepository,
        private readonly TenantConfigurationRepository $tenantConfigurationRepository,
        private readonly AppointmentRepository $appointmentRepository,
        private readonly AvailabilityRuleRepository $availabilityRuleRepository,
        private readonly AvailabilityCacheInvalidator $availabilityCacheInvalidator,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function get(string $providerId, string $dateFrom, string $dateTo, ?int $limit = null): AvailabilitySlotResultData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $provider = $this->providerOrFail($tenantId, $providerId);
        $normalized = $this->normalizedSlotWindow($dateFrom, $dateTo, $limit);

        return $this->rememberSlotResult($tenantId, $provider, $normalized);
    }

    public function rebuild(string $providerId, string $dateFrom, string $dateTo, ?int $limit = null): AvailabilitySlotResultData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $provider = $this->providerOrFail($tenantId, $providerId);
        $normalized = $this->normalizedSlotWindow($dateFrom, $dateTo, $limit);

        $this->availabilityCacheInvalidator->invalidate($tenantId);
        $result = $this->rememberSlotResult($tenantId, $provider, $normalized);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'availability.cache_rebuilt',
            objectType: 'provider',
            objectId: $provider->providerId,
            after: $result->toArray(),
            metadata: [
                'date_from' => $normalized['date_from']->toDateString(),
                'date_to' => $normalized['date_to']->toDateString(),
                'limit' => $normalized['limit'],
            ],
        ));

        return $result;
    }

    /**
     * @param  list<string>  $excludedAppointmentIds
     */
    public function isSlotAvailable(
        string $providerId,
        CarbonImmutable $startAt,
        CarbonImmutable $endAt,
        string $timezone,
        array $excludedAppointmentIds = [],
    ): bool {
        $tenantId = $this->tenantContext->requireTenantId();
        $provider = $this->providerOrFail($tenantId, $providerId);
        $clinic = $provider->clinicId !== null
            ? $this->clinicRepository->findClinic($tenantId, $provider->clinicId)
            : null;
        $clinicSettings = $clinic !== null ? $this->clinicRepository->settings($tenantId, $clinic->clinicId) : null;
        $resolvedTimezone = $this->resolvedTimezone($tenantId, $clinicSettings);
        $requestedStart = $startAt->setTimezone($resolvedTimezone);
        $requestedEnd = $endAt->setTimezone($resolvedTimezone);

        if ($requestedStart->toDateString() !== $requestedEnd->toDateString()) {
            return false;
        }

        if ($clinic !== null && $clinic->status !== ClinicStatus::ACTIVE) {
            return false;
        }

        $date = CarbonImmutable::createFromFormat('Y-m-d', $requestedStart->toDateString(), 'UTC')
            ?: throw new LogicException('Availability date could not be created.');
        $date = $date->startOfDay();
        $rules = $this->availabilityRuleRepository->listRelevantForDateRange(
            tenantId: $tenantId,
            providerId: $provider->providerId,
            dateFrom: $date,
            dateTo: $date,
        );
        $blockingAppointments = array_values(array_filter(
            $this->appointmentRepository->listBlockingForProviderWindow(
                tenantId: $tenantId,
                providerId: $provider->providerId,
                windowStart: $requestedStart->startOfDay(),
                windowEnd: $requestedStart->endOfDay(),
            ),
            static fn (AppointmentData $appointment): bool => ! in_array($appointment->appointmentId, $excludedAppointmentIds, true),
        ));
        $workHours = $clinic !== null ? $this->clinicRepository->workHours($tenantId, $clinic->clinicId) : null;
        $holidays = $clinic !== null ? $this->clinicRepository->listHolidays($tenantId, $clinic->clinicId) : [];
        $slotDuration = $clinicSettings !== null ? $clinicSettings->defaultAppointmentDurationMinutes : 30;
        $slotInterval = $clinicSettings !== null ? $clinicSettings->slotIntervalMinutes : 15;
        $candidateSlots = $this->buildDaySlots(
            date: $date,
            timezone: $resolvedTimezone,
            slotDuration: $slotDuration,
            slotInterval: $slotInterval,
            rules: $rules,
            blockingAppointments: $blockingAppointments,
            workHours: $workHours,
            holidays: $holidays,
            limit: 1000,
        );

        foreach ($candidateSlots as $slot) {
            if ($slot->startAt->equalTo($requestedStart) && $slot->endAt->equalTo($requestedEnd)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{
     *      date_from: CarbonImmutable,
     *      date_to: CarbonImmutable,
     *      limit: int
     *  }  $window
     */
    private function buildSlotResult(string $tenantId, ProviderData $provider, array $window): AvailabilitySlotResultData
    {
        $clinic = $provider->clinicId !== null
            ? $this->clinicRepository->findClinic($tenantId, $provider->clinicId)
            : null;
        $clinicSettings = $clinic !== null ? $this->clinicRepository->settings($tenantId, $clinic->clinicId) : null;
        $timezone = $this->resolvedTimezone($tenantId, $clinicSettings);
        $slotDuration = $clinicSettings !== null ? $clinicSettings->defaultAppointmentDurationMinutes : 30;
        $slotInterval = $clinicSettings !== null ? $clinicSettings->slotIntervalMinutes : 15;

        if ($clinic !== null && $clinic->status !== ClinicStatus::ACTIVE) {
            return $this->emptySlotResult(
                providerId: $provider->providerId,
                timezone: $timezone,
                dateFrom: $window['date_from'],
                dateTo: $window['date_to'],
                slotDuration: $slotDuration,
                slotInterval: $slotInterval,
            );
        }

        $rules = $this->availabilityRuleRepository->listRelevantForDateRange(
            tenantId: $tenantId,
            providerId: $provider->providerId,
            dateFrom: $window['date_from'],
            dateTo: $window['date_to'],
        );
        $blockingAppointments = $this->appointmentRepository->listBlockingForProviderWindow(
            tenantId: $tenantId,
            providerId: $provider->providerId,
            windowStart: (CarbonImmutable::createFromFormat('Y-m-d', $window['date_from']->toDateString(), $timezone)
                ?: throw new LogicException('Blocking window start could not be created.'))
                ->startOfDay(),
            windowEnd: ((CarbonImmutable::createFromFormat('Y-m-d', $window['date_to']->toDateString(), $timezone)
                ?: throw new LogicException('Blocking window end could not be created.'))
                ->startOfDay())
                ->endOfDay(),
        );
        $workHours = $clinic !== null ? $this->clinicRepository->workHours($tenantId, $clinic->clinicId) : null;
        $holidays = $clinic !== null ? $this->clinicRepository->listHolidays($tenantId, $clinic->clinicId) : [];
        $slots = [];

        for ($date = $window['date_from']; $date->lessThanOrEqualTo($window['date_to']); $date = $date->addDay()) {
            $remaining = $window['limit'] - count($slots);

            if ($remaining <= 0) {
                break;
            }

            $daySlots = $this->buildDaySlots(
                $date,
                $timezone,
                $slotDuration,
                $slotInterval,
                $rules,
                $blockingAppointments,
                $workHours,
                $holidays,
                $remaining,
            );

            foreach ($daySlots as $slot) {
                $slots[] = $slot;

                if (count($slots) >= $window['limit']) {
                    break 2;
                }
            }
        }

        return new AvailabilitySlotResultData(
            providerId: $provider->providerId,
            timezone: $timezone,
            dateFrom: $window['date_from']->toDateString(),
            dateTo: $window['date_to']->toDateString(),
            slotDurationMinutes: $slotDuration,
            slotIntervalMinutes: $slotInterval,
            generatedAt: CarbonImmutable::now($timezone),
            slots: $slots,
        );
    }

    /**
     * @param  list<AvailabilityRuleData>  $rules
     * @param  list<AppointmentData>  $blockingAppointments
     * @param  list<ClinicHolidayData>  $holidays
     * @return list<AvailabilitySlotData>
     */
    private function buildDaySlots(
        CarbonImmutable $date,
        string $timezone,
        int $slotDuration,
        int $slotInterval,
        array $rules,
        array $blockingAppointments,
        ?ClinicWorkHoursData $workHours,
        array $holidays,
        int $limit,
    ): array {
        if ($limit <= 0 || $this->isClosedHoliday($date, $holidays)) {
            return [];
        }

        $weekday = AvailabilityWeekday::fromDate($date);
        $availableIntervals = [];
        $unavailableIntervals = [];

        foreach ($rules as $rule) {
            if (! $this->ruleAppliesToDate($rule, $date, $weekday)) {
                continue;
            }

            $interval = [
                'start' => $this->timeToMinutes($rule->startTime),
                'end' => $this->timeToMinutes($rule->endTime),
                'source_rule_ids' => [$rule->ruleId],
            ];

            if ($rule->availabilityType === AvailabilityType::AVAILABLE) {
                $availableIntervals[] = $interval;
            } else {
                $unavailableIntervals[] = $interval;
            }
        }

        $effectiveIntervals = $this->mergeIntervals($availableIntervals);
        $effectiveIntervals = $this->subtractIntervals($effectiveIntervals, $this->mergeIntervals($unavailableIntervals));
        $effectiveIntervals = $this->subtractIntervals(
            $effectiveIntervals,
            $this->mergeIntervals($this->blockingIntervalsForDate($blockingAppointments, $date, $timezone)),
        );

        if ($workHours !== null) {
            $effectiveIntervals = $this->intersectIntervals(
                $effectiveIntervals,
                $this->clinicWorkIntervals($workHours, $weekday),
            );
        }

        return $this->slotsFromIntervals($date, $timezone, $slotDuration, $slotInterval, $effectiveIntervals, $limit);
    }

    /**
     * @param  list<AppointmentData>  $appointments
     * @return list<array{start: int, end: int, source_rule_ids: list<string>}>
     */
    private function blockingIntervalsForDate(array $appointments, CarbonImmutable $date, string $timezone): array
    {
        $intervals = [];
        $dayStart = (CarbonImmutable::createFromFormat('Y-m-d', $date->toDateString(), $timezone)
            ?: throw new LogicException('Day start could not be created for blocking intervals.'))
            ->startOfDay();
        $dayEnd = $dayStart->addDay();

        foreach ($appointments as $appointment) {
            $appointmentStart = $appointment->scheduledStartAt->setTimezone($timezone);
            $appointmentEnd = $appointment->scheduledEndAt->setTimezone($timezone);

            if ($appointmentEnd->lessThanOrEqualTo($dayStart) || $appointmentStart->greaterThanOrEqualTo($dayEnd)) {
                continue;
            }

            $effectiveStart = $appointmentStart->greaterThan($dayStart) ? $appointmentStart : $dayStart;
            $effectiveEnd = $appointmentEnd->lessThan($dayEnd) ? $appointmentEnd : $dayEnd;

            $intervals[] = [
                'start' => ($effectiveStart->hour * 60) + $effectiveStart->minute,
                'end' => ($effectiveEnd->hour * 60) + $effectiveEnd->minute,
                'source_rule_ids' => [],
            ];
        }

        return $intervals;
    }

    /**
     * @return list<array{start: int, end: int, source_rule_ids: list<string>}>
     */
    private function clinicWorkIntervals(ClinicWorkHoursData $workHours, string $weekday): array
    {
        $intervals = [];

        foreach ($workHours->days[$weekday] ?? [] as $interval) {
            $intervals[] = [
                'start' => $this->timeToMinutes($interval['start_time']),
                'end' => $this->timeToMinutes($interval['end_time']),
                'source_rule_ids' => [],
            ];
        }

        return $intervals;
    }

    private function emptySlotResult(
        string $providerId,
        string $timezone,
        CarbonImmutable $dateFrom,
        CarbonImmutable $dateTo,
        int $slotDuration,
        int $slotInterval,
    ): AvailabilitySlotResultData {
        return new AvailabilitySlotResultData(
            providerId: $providerId,
            timezone: $timezone,
            dateFrom: $dateFrom->toDateString(),
            dateTo: $dateTo->toDateString(),
            slotDurationMinutes: $slotDuration,
            slotIntervalMinutes: $slotInterval,
            generatedAt: CarbonImmutable::now($timezone),
            slots: [],
        );
    }

    /**
     * @param  list<ClinicHolidayData>  $holidays
     */
    private function isClosedHoliday(CarbonImmutable $date, array $holidays): bool
    {
        foreach ($holidays as $holiday) {
            if (
                $holiday->isClosed
                && $date->betweenIncluded($holiday->startDate->startOfDay(), $holiday->endDate->startOfDay())
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{start: int, end: int, source_rule_ids: list<string>}>  $available
     * @param  list<array{start: int, end: int, source_rule_ids: list<string>}>  $unavailable
     * @return list<array{start: int, end: int, source_rule_ids: list<string>}>
     */
    private function subtractIntervals(array $available, array $unavailable): array
    {
        $result = $available;

        foreach ($unavailable as $blocked) {
            $next = [];

            foreach ($result as $segment) {
                if ($blocked['end'] <= $segment['start'] || $blocked['start'] >= $segment['end']) {
                    $next[] = $segment;

                    continue;
                }

                if ($blocked['start'] > $segment['start']) {
                    $next[] = [
                        'start' => $segment['start'],
                        'end' => min($blocked['start'], $segment['end']),
                        'source_rule_ids' => $segment['source_rule_ids'],
                    ];
                }

                if ($blocked['end'] < $segment['end']) {
                    $next[] = [
                        'start' => max($blocked['end'], $segment['start']),
                        'end' => $segment['end'],
                        'source_rule_ids' => $segment['source_rule_ids'],
                    ];
                }
            }

            $result = array_values(array_filter(
                $next,
                static fn (array $interval): bool => $interval['start'] < $interval['end'],
            ));
        }

        return $result;
    }

    /**
     * @param  list<array{start: int, end: int, source_rule_ids: list<string>}>  $left
     * @param  list<array{start: int, end: int, source_rule_ids: list<string>}>  $right
     * @return list<array{start: int, end: int, source_rule_ids: list<string>}>
     */
    private function intersectIntervals(array $left, array $right): array
    {
        $intersections = [];

        foreach ($left as $base) {
            foreach ($right as $constraint) {
                $start = max($base['start'], $constraint['start']);
                $end = min($base['end'], $constraint['end']);

                if ($start >= $end) {
                    continue;
                }

                $intersections[] = [
                    'start' => $start,
                    'end' => $end,
                    'source_rule_ids' => $base['source_rule_ids'],
                ];
            }
        }

        return $this->mergeIntervals($intersections);
    }

    /**
     * @param  list<array{start: int, end: int, source_rule_ids: list<string>}>  $intervals
     * @return list<array{start: int, end: int, source_rule_ids: list<string>}>
     */
    private function mergeIntervals(array $intervals): array
    {
        if ($intervals === []) {
            return [];
        }

        usort($intervals, function (array $left, array $right): int {
            if ($left['start'] !== $right['start']) {
                return $left['start'] <=> $right['start'];
            }

            return $left['end'] <=> $right['end'];
        });

        /** @var array{start: int, end: int, source_rule_ids: list<string>} $current */
        $current = array_shift($intervals);

        $merged = [];

        foreach ($intervals as $interval) {
            if ($interval['start'] <= $current['end']) {
                $current['end'] = max($current['end'], $interval['end']);
                $current['source_rule_ids'] = $this->mergedSourceRuleIds(
                    $current['source_rule_ids'],
                    $interval['source_rule_ids'],
                );

                continue;
            }

            $merged[] = $current;
            $current = $interval;
        }

        $merged[] = $current;

        return $merged;
    }

    /**
     * @param  list<string>  $left
     * @param  list<string>  $right
     * @return list<string>
     */
    private function mergedSourceRuleIds(array $left, array $right): array
    {
        $merged = array_values(array_unique([...$left, ...$right]));
        sort($merged);

        return $merged;
    }

    /**
     * @param  array{
     *      date_from: CarbonImmutable,
     *      date_to: CarbonImmutable,
     *      limit: int
     *  }  $window
     */
    private function rememberSlotResult(string $tenantId, ProviderData $provider, array $window): AvailabilitySlotResultData
    {
        /** @var AvailabilitySlotResultData $result */
        $result = $this->tenantCache->remember(
            domain: 'availability',
            segments: [
                'provider',
                $provider->providerId,
                $window['date_from']->toDateString(),
                $window['date_to']->toDateString(),
                $window['limit'],
            ],
            tenantId: $tenantId,
            ttl: null,
            callback: fn (): AvailabilitySlotResultData => $this->buildSlotResult($tenantId, $provider, $window),
        );

        return $result;
    }

    /**
     * @return array{
     *      date_from: CarbonImmutable,
     *      date_to: CarbonImmutable,
     *      limit: int
     * }
     */
    private function normalizedSlotWindow(string $dateFrom, string $dateTo, ?int $limit): array
    {
        $from = CarbonImmutable::createFromFormat('Y-m-d', trim($dateFrom), 'UTC')
            ?: throw new UnprocessableEntityHttpException('`date_from` must use `Y-m-d` format.');
        $to = CarbonImmutable::createFromFormat('Y-m-d', trim($dateTo), 'UTC')
            ?: throw new UnprocessableEntityHttpException('`date_to` must use `Y-m-d` format.');
        $resolvedLimit = $limit ?? 200;

        if ($to->lessThan($from)) {
            throw new UnprocessableEntityHttpException('`date_to` must not be earlier than `date_from`.');
        }

        if ($from->diffInDays($to) > 30) {
            throw new UnprocessableEntityHttpException('Availability slot windows may span at most 31 calendar days.');
        }

        if ($resolvedLimit < 1 || $resolvedLimit > 1000) {
            throw new UnprocessableEntityHttpException('`limit` must be between 1 and 1000.');
        }

        return [
            'date_from' => $from->startOfDay(),
            'date_to' => $to->startOfDay(),
            'limit' => $resolvedLimit,
        ];
    }

    private function providerOrFail(string $tenantId, string $providerId): ProviderData
    {
        $provider = $this->providerRepository->findInTenant($tenantId, $providerId);

        if (! $provider instanceof ProviderData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $provider;
    }

    private function resolvedTimezone(string $tenantId, ?ClinicSettingsData $clinicSettings): string
    {
        $tenantTimezone = $this->tenantConfigurationRepository->settings($tenantId)->timezone;
        /** @var mixed $configuredTimezone */
        $configuredTimezone = config('app.timezone');
        $clinicTimezone = $clinicSettings !== null ? $clinicSettings->timezone : null;

        if ($clinicTimezone !== null && $clinicTimezone !== '') {
            return $clinicTimezone;
        }

        if ($tenantTimezone !== null && $tenantTimezone !== '') {
            return $tenantTimezone;
        }

        return is_string($configuredTimezone) && $configuredTimezone !== '' ? $configuredTimezone : 'UTC';
    }

    private function ruleAppliesToDate(AvailabilityRuleData $rule, CarbonImmutable $date, string $weekday): bool
    {
        if ($rule->scopeType === 'weekly') {
            return $rule->weekday === $weekday;
        }

        return $rule->specificDate?->toDateString() === $date->toDateString();
    }

    /**
     * @param  list<array{start: int, end: int, source_rule_ids: list<string>}>  $intervals
     * @return list<AvailabilitySlotData>
     */
    private function slotsFromIntervals(
        CarbonImmutable $date,
        string $timezone,
        int $slotDuration,
        int $slotInterval,
        array $intervals,
        int $limit,
    ): array {
        $slots = [];

        foreach ($intervals as $interval) {
            for ($start = $interval['start']; $start + $slotDuration <= $interval['end']; $start += $slotInterval) {
                $startAt = $this->slotDateTime($date, $start, $timezone);
                $slots[] = new AvailabilitySlotData(
                    startAt: $startAt,
                    endAt: $startAt->addMinutes($slotDuration),
                    date: $date->toDateString(),
                    sourceRuleIds: $interval['source_rule_ids'],
                );

                if (count($slots) >= $limit) {
                    return $slots;
                }
            }
        }

        return $slots;
    }

    private function slotDateTime(CarbonImmutable $date, int $minutesFromMidnight, string $timezone): CarbonImmutable
    {
        $formatted = sprintf(
            '%s %02d:%02d',
            $date->toDateString(),
            intdiv($minutesFromMidnight, 60),
            $minutesFromMidnight % 60,
        );

        $dateTime = CarbonImmutable::createFromFormat('Y-m-d H:i', $formatted, $timezone);

        if (! $dateTime instanceof CarbonImmutable) {
            throw new LogicException('Slot timestamps could not be created for the resolved timezone.');
        }

        return $dateTime;
    }

    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time, 2);
        $hours = (int) $parts[0];
        $minutes = isset($parts[1]) ? (int) $parts[1] : 0;

        return ($hours * 60) + $minutes;
    }
}
