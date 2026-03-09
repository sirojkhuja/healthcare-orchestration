<?php

namespace App\Modules\Scheduling\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Provider\Application\Contracts\ProviderRepository;
use App\Modules\Provider\Application\Data\ProviderData;
use App\Modules\Scheduling\Application\Contracts\AvailabilityRuleRepository;
use App\Modules\Scheduling\Application\Data\AvailabilityRuleData;
use App\Modules\Scheduling\Application\Data\AvailabilitySlotData;
use App\Modules\Scheduling\Application\Data\AvailabilitySlotResultData;
use App\Modules\Scheduling\Application\Data\ProviderCalendarData;
use App\Modules\Scheduling\Application\Data\ProviderCalendarDayData;
use App\Modules\Scheduling\Application\Data\ProviderCalendarExportData;
use App\Modules\Scheduling\Application\Data\ProviderCalendarTimeOffData;
use App\Modules\Scheduling\Application\Data\ProviderCalendarWindowData;
use App\Modules\Scheduling\Domain\Availability\AvailabilityScopeType;
use App\Modules\Scheduling\Domain\Availability\AvailabilityType;
use App\Modules\Scheduling\Domain\Availability\AvailabilityWeekday;
use App\Modules\TenantManagement\Application\Contracts\ClinicRepository;
use App\Shared\Application\Contracts\FileStorageManager;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ProviderCalendarService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ProviderRepository $providerRepository,
        private readonly ClinicRepository $clinicRepository,
        private readonly AvailabilityRuleRepository $availabilityRuleRepository,
        private readonly AvailabilitySlotService $availabilitySlotService,
        private readonly FileStorageManager $fileStorageManager,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function export(
        string $providerId,
        string $dateFrom,
        string $dateTo,
        ?int $limit = null,
        string $format = 'csv',
    ): ProviderCalendarExportData {
        if ($format !== 'csv') {
            throw new UnprocessableEntityHttpException('Provider calendar export currently supports only csv format.');
        }

        $tenantId = $this->tenantContext->requireTenantId();
        $calendar = $this->get($providerId, $dateFrom, $dateTo, $limit);
        $fileName = sprintf('provider-calendar-%s-%s.csv', $providerId, $calendar->generatedAt->format('Ymd-His'));
        $stored = $this->fileStorageManager->storeExport(
            $this->buildCsv($calendar),
            sprintf(
                'tenants/%s/providers/%s/calendar/exports/%s/%s',
                $tenantId,
                $providerId,
                $calendar->generatedAt->format('Y/m/d'),
                $fileName,
            ),
        );
        $export = new ProviderCalendarExportData(
            exportId: (string) Str::uuid(),
            providerId: $providerId,
            format: $format,
            fileName: $fileName,
            rowCount: count($calendar->days),
            generatedAt: $calendar->generatedAt,
            filters: new ProviderCalendarWindowData(
                dateFrom: $calendar->dateFrom,
                dateTo: $calendar->dateTo,
                limit: $this->normalizedLimit($limit),
            ),
            disk: $stored->disk,
            path: $stored->path,
            visibility: $stored->visibility,
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'providers.calendar_exported',
            objectType: 'provider_calendar_export',
            objectId: $export->exportId,
            after: $export->toArray(),
            metadata: [
                'provider_id' => $providerId,
                'tenant_id' => $tenantId,
                'filters' => $export->filters->toArray(),
            ],
        ));

        return $export;
    }

    public function get(string $providerId, string $dateFrom, string $dateTo, ?int $limit = null): ProviderCalendarData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $provider = $this->providerOrFail($tenantId, $providerId);
        $window = $this->normalizedWindow($dateFrom, $dateTo, $limit);
        $slotResult = $this->availabilitySlotService->get(
            providerId: $providerId,
            dateFrom: $window->dateFrom,
            dateTo: $window->dateTo,
            limit: $window->limit,
        );
        $rules = $this->availabilityRuleRepository->listRelevantForDateRange(
            tenantId: $tenantId,
            providerId: $provider->providerId,
            dateFrom: CarbonImmutable::createFromFormat('Y-m-d', $window->dateFrom, 'UTC')
                ?: throw new UnprocessableEntityHttpException('Calendar date_from must use Y-m-d format.'),
            dateTo: CarbonImmutable::createFromFormat('Y-m-d', $window->dateTo, 'UTC')
                ?: throw new UnprocessableEntityHttpException('Calendar date_to must use Y-m-d format.'),
        );

        return new ProviderCalendarData(
            providerId: $provider->providerId,
            timezone: $slotResult->timezone,
            dateFrom: $slotResult->dateFrom,
            dateTo: $slotResult->dateTo,
            slotDurationMinutes: $slotResult->slotDurationMinutes,
            slotIntervalMinutes: $slotResult->slotIntervalMinutes,
            generatedAt: $slotResult->generatedAt,
            days: $this->buildDays($tenantId, $provider, $rules, $slotResult),
        );
    }

    /**
     * @param  list<AvailabilityRuleData>  $rules
     * @return list<ProviderCalendarDayData>
     */
    private function buildDays(
        string $tenantId,
        ProviderData $provider,
        array $rules,
        AvailabilitySlotResultData $slotResult,
    ): array {
        $weeklyRules = array_values(array_filter(
            $rules,
            static fn (AvailabilityRuleData $rule): bool => $rule->scopeType === AvailabilityScopeType::WEEKLY,
        ));
        $timeOffByDate = $this->timeOffByDate($rules);
        $slotsByDate = $this->slotsByDate($slotResult->slots);
        $closedHolidayDates = $this->closedHolidayDates($tenantId, $provider);
        $days = [];
        $startDate = CarbonImmutable::createFromFormat('Y-m-d', $slotResult->dateFrom, 'UTC')
            ?: throw new UnprocessableEntityHttpException('Calendar date_from must use Y-m-d format.');
        $endDate = CarbonImmutable::createFromFormat('Y-m-d', $slotResult->dateTo, 'UTC')
            ?: throw new UnprocessableEntityHttpException('Calendar date_to must use Y-m-d format.');

        for (
            $date = $startDate;
            $date->lessThanOrEqualTo($endDate);
            $date = $date->addDay()
        ) {
            $weekday = AvailabilityWeekday::fromDate($date);
            $dateKey = $date->toDateString();
            $timeOff = $timeOffByDate[$dateKey] ?? [];
            $slots = $slotsByDate[$dateKey] ?? [];

            $days[] = new ProviderCalendarDayData(
                date: $dateKey,
                weekday: $weekday,
                isClinicClosed: in_array($dateKey, $closedHolidayDates, true),
                workHours: $this->dayIntervalsFromRules($weekday, $weeklyRules),
                timeOff: $timeOff,
                slotCount: count($slots),
                slots: $slots,
            );
        }

        return $days;
    }

    private function buildCsv(ProviderCalendarData $calendar): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new UnprocessableEntityHttpException('Provider calendar export could not allocate a CSV stream.');
        }

        fputcsv($stream, [
            'date',
            'weekday',
            'is_clinic_closed',
            'work_hours',
            'time_off',
            'slot_count',
            'slot_start_times',
            'slot_end_times',
        ]);

        foreach ($calendar->days as $day) {
            fputcsv($stream, [
                $day->date,
                $day->weekday,
                $day->isClinicClosed ? 'true' : 'false',
                $this->serializeIntervals($day->workHours),
                $this->serializeTimeOff($day->timeOff),
                $day->slotCount,
                implode('|', array_map(static fn (AvailabilitySlotData $slot): string => $slot->startAt->toIso8601String(), $day->slots)),
                implode('|', array_map(static fn (AvailabilitySlotData $slot): string => $slot->endAt->toIso8601String(), $day->slots)),
            ]);
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if (! is_string($contents)) {
            throw new UnprocessableEntityHttpException('Provider calendar export could not be generated.');
        }

        return $contents;
    }

    /**
     * @return list<string>
     */
    private function closedHolidayDates(string $tenantId, ProviderData $provider): array
    {
        if ($provider->clinicId === null) {
            return [];
        }

        $closedDates = [];

        foreach ($this->clinicRepository->listHolidays($tenantId, $provider->clinicId) as $holiday) {
            if (! $holiday->isClosed) {
                continue;
            }

            for ($date = $holiday->startDate->startOfDay(); $date->lessThanOrEqualTo($holiday->endDate->startOfDay()); $date = $date->addDay()) {
                $closedDates[] = $date->toDateString();
            }
        }

        return array_values(array_unique($closedDates));
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

    private function minutesToTime(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return sprintf('%02d:%02d', $hours, $mins);
    }

    private function normalizedLimit(?int $limit): int
    {
        return $limit ?? 200;
    }

    private function normalizedWindow(string $dateFrom, string $dateTo, ?int $limit = null): ProviderCalendarWindowData
    {
        $from = CarbonImmutable::createFromFormat('Y-m-d', $dateFrom, 'UTC');
        $to = CarbonImmutable::createFromFormat('Y-m-d', $dateTo, 'UTC');

        if (! $from instanceof CarbonImmutable || ! $to instanceof CarbonImmutable) {
            throw new UnprocessableEntityHttpException('Calendar windows must use Y-m-d dates.');
        }

        if ($to->lessThan($from)) {
            throw new UnprocessableEntityHttpException('Calendar date_to must not be earlier than date_from.');
        }

        if ($from->diffInDays($to) >= 31) {
            throw new UnprocessableEntityHttpException('Calendar windows may span at most 31 calendar days.');
        }

        $normalizedLimit = $this->normalizedLimit($limit);

        if ($normalizedLimit < 1 || $normalizedLimit > 1000) {
            throw new UnprocessableEntityHttpException('Calendar limit must be between 1 and 1000.');
        }

        return new ProviderCalendarWindowData(
            dateFrom: $from->toDateString(),
            dateTo: $to->toDateString(),
            limit: $normalizedLimit,
        );
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

    private function providerOrFail(string $tenantId, string $providerId): ProviderData
    {
        $provider = $this->providerRepository->findInTenant($tenantId, $providerId);

        if (! $provider instanceof ProviderData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $provider;
    }

    /**
     * @param  list<array{start_time: string, end_time: string}>  $intervals
     */
    private function serializeIntervals(array $intervals): string
    {
        return implode('|', array_map(
            static fn (array $interval): string => $interval['start_time'].'-'.$interval['end_time'],
            $intervals,
        ));
    }

    /**
     * @param  list<ProviderCalendarTimeOffData>  $timeOff
     */
    private function serializeTimeOff(array $timeOff): string
    {
        return implode('|', array_map(
            static fn (ProviderCalendarTimeOffData $item): string => trim($item->specificDate.' '.$item->startTime.'-'.$item->endTime.' '.($item->notes ?? '')),
            $timeOff,
        ));
    }

    /**
     * @param  list<AvailabilitySlotData>  $slots
     * @return array<string, list<AvailabilitySlotData>>
     */
    private function slotsByDate(array $slots): array
    {
        $grouped = [];

        foreach ($slots as $slot) {
            $grouped[$slot->date] ??= [];
            $grouped[$slot->date][] = $slot;
        }

        return $grouped;
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

    private function timeToMinutes(string $value): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $value));

        return ($hours * 60) + $minutes;
    }

    /**
     * @param  list<AvailabilityRuleData>  $rules
     * @return array<string, list<ProviderCalendarTimeOffData>>
     */
    private function timeOffByDate(array $rules): array
    {
        $grouped = [];

        foreach ($rules as $rule) {
            if (
                $rule->scopeType !== AvailabilityScopeType::DATE
                || $rule->availabilityType !== AvailabilityType::UNAVAILABLE
                || ! $rule->specificDate instanceof CarbonImmutable
            ) {
                continue;
            }

            $date = $rule->specificDate->toDateString();
            $grouped[$date] ??= [];
            $grouped[$date][] = new ProviderCalendarTimeOffData(
                timeOffId: $rule->ruleId,
                specificDate: $date,
                startTime: $rule->startTime,
                endTime: $rule->endTime,
                notes: $rule->notes,
                createdAt: $rule->createdAt,
                updatedAt: $rule->updatedAt,
            );
        }

        return $grouped;
    }
}
