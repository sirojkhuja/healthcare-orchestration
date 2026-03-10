<?php

namespace App\Modules\Scheduling\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Provider\Application\Contracts\ProviderRepository;
use App\Modules\Provider\Application\Data\ProviderData;
use App\Modules\Scheduling\Application\Contracts\AvailabilityCacheInvalidator;
use App\Modules\Scheduling\Application\Contracts\AvailabilityRuleRepository;
use App\Modules\Scheduling\Application\Data\AvailabilityRuleData;
use App\Modules\Scheduling\Domain\Availability\AvailabilityScopeType;
use App\Modules\Scheduling\Domain\Availability\AvailabilityType;
use App\Modules\Scheduling\Domain\Availability\AvailabilityWeekday;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class AvailabilityRuleService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ProviderRepository $providerRepository,
        private readonly AvailabilityRuleRepository $availabilityRuleRepository,
        private readonly AvailabilityCacheInvalidator $availabilityCacheInvalidator,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $providerId, array $attributes): AvailabilityRuleData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $provider = $this->providerOrFail($tenantId, $providerId);
        $normalized = $this->normalizedRuleAttributes($attributes);
        $this->assertNoConflict($tenantId, $provider->providerId, $normalized);

        $rule = $this->availabilityRuleRepository->create($tenantId, $provider->providerId, $normalized);
        $this->availabilityCacheInvalidator->invalidate($tenantId);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'availability.rules.created',
            objectType: 'availability_rule',
            objectId: $rule->ruleId,
            after: $rule->toArray(),
            metadata: ['provider_id' => $provider->providerId],
        ));

        return $rule;
    }

    public function delete(string $providerId, string $ruleId): AvailabilityRuleData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $provider = $this->providerOrFail($tenantId, $providerId);
        $rule = $this->ruleOrFail($tenantId, $provider->providerId, $ruleId);

        if (! $this->availabilityRuleRepository->delete($tenantId, $provider->providerId, $rule->ruleId)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->availabilityCacheInvalidator->invalidate($tenantId);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'availability.rules.deleted',
            objectType: 'availability_rule',
            objectId: $rule->ruleId,
            before: $rule->toArray(),
            metadata: ['provider_id' => $provider->providerId],
        ));

        return $rule;
    }

    /**
     * @return list<AvailabilityRuleData>
     */
    public function listForProvider(string $providerId): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $provider = $this->providerOrFail($tenantId, $providerId);

        return $this->availabilityRuleRepository->listForProvider($tenantId, $provider->providerId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $providerId, string $ruleId, array $attributes): AvailabilityRuleData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $provider = $this->providerOrFail($tenantId, $providerId);
        $rule = $this->ruleOrFail($tenantId, $provider->providerId, $ruleId);
        $candidate = $this->mergedRuleAttributes($rule, $attributes);
        $updates = $this->changedAttributes($rule, $candidate);

        if ($updates === []) {
            return $rule;
        }

        $this->assertNoConflict($tenantId, $provider->providerId, $candidate, $rule->ruleId);
        $updated = $this->availabilityRuleRepository->update($tenantId, $provider->providerId, $rule->ruleId, $updates);

        if (! $updated instanceof AvailabilityRuleData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->availabilityCacheInvalidator->invalidate($tenantId);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'availability.rules.updated',
            objectType: 'availability_rule',
            objectId: $updated->ruleId,
            before: $rule->toArray(),
            after: $updated->toArray(),
            metadata: ['provider_id' => $provider->providerId],
        ));

        return $updated;
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
    private function assertNoConflict(
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
            throw new ConflictHttpException('The rule overlaps an existing availability rule of the same type and scope.');
        }
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
     *      scope_type?: string,
     *      availability_type?: string,
     *      weekday?: string|null,
     *      specific_date?: CarbonImmutable|null,
     *      start_time?: string,
     *      end_time?: string,
     *      notes?: string|null
     * }
     */
    private function changedAttributes(AvailabilityRuleData $rule, array $candidate): array
    {
        $updates = [];

        if ($candidate['scope_type'] !== $rule->scopeType) {
            $updates['scope_type'] = $candidate['scope_type'];
        }

        if ($candidate['availability_type'] !== $rule->availabilityType) {
            $updates['availability_type'] = $candidate['availability_type'];
        }

        if ($candidate['weekday'] !== $rule->weekday) {
            $updates['weekday'] = $candidate['weekday'];
        }

        if ($this->dateValue($candidate['specific_date']) !== $this->dateValue($rule->specificDate)) {
            $updates['specific_date'] = $candidate['specific_date'];
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

    private function dateValue(?CarbonImmutable $date): ?string
    {
        return $date?->toDateString();
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
     * }
     */
    private function mergedRuleAttributes(AvailabilityRuleData $rule, array $attributes): array
    {
        return $this->normalizedRuleAttributes([
            'scope_type' => array_key_exists('scope_type', $attributes) ? $attributes['scope_type'] : $rule->scopeType,
            'availability_type' => array_key_exists('availability_type', $attributes) ? $attributes['availability_type'] : $rule->availabilityType,
            'weekday' => array_key_exists('weekday', $attributes) ? $attributes['weekday'] : $rule->weekday,
            'specific_date' => array_key_exists('specific_date', $attributes)
                ? $attributes['specific_date']
                : $rule->specificDate?->toDateString(),
            'start_time' => array_key_exists('start_time', $attributes) ? $attributes['start_time'] : $rule->startTime,
            'end_time' => array_key_exists('end_time', $attributes) ? $attributes['end_time'] : $rule->endTime,
            'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : $rule->notes,
        ]);
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
     * }
     */
    private function normalizedRuleAttributes(array $attributes): array
    {
        $scopeType = $this->normalizedEnum($attributes['scope_type'] ?? null, AvailabilityScopeType::all(), 'scope_type');
        $availabilityType = $this->normalizedEnum($attributes['availability_type'] ?? null, AvailabilityType::all(), 'availability_type');
        $weekday = $this->nullableNormalizedEnum($attributes['weekday'] ?? null, AvailabilityWeekday::all(), 'weekday');
        $specificDate = $this->nullableDate($attributes['specific_date'] ?? null);
        $startTime = $this->normalizedTime($attributes['start_time'] ?? null, 'start_time');
        $endTime = $this->normalizedTime($attributes['end_time'] ?? null, 'end_time');
        $notes = $this->nullableTrimmedString($attributes['notes'] ?? null);

        if ($scopeType === AvailabilityScopeType::WEEKLY) {
            if ($weekday === null || $specificDate !== null) {
                throw new UnprocessableEntityHttpException('Weekly rules require `weekday` and forbid `specific_date`.');
            }
        }

        if ($scopeType === AvailabilityScopeType::DATE) {
            if ($specificDate === null || $weekday !== null) {
                throw new UnprocessableEntityHttpException('Date rules require `specific_date` and forbid `weekday`.');
            }
        }

        if ($this->timeToMinutes($startTime) >= $this->timeToMinutes($endTime)) {
            throw new UnprocessableEntityHttpException('`start_time` must be earlier than `end_time`.');
        }

        return [
            'scope_type' => $scopeType,
            'availability_type' => $availabilityType,
            'weekday' => $weekday,
            'specific_date' => $specificDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'notes' => $notes,
        ];
    }

    /**
     * @param  list<string>  $allowedValues
     */
    private function normalizedEnum(mixed $value, array $allowedValues, string $field): string
    {
        $normalized = strtolower(trim($this->requiredString($value, $field)));

        if (! in_array($normalized, $allowedValues, true)) {
            throw new UnprocessableEntityHttpException("`{$field}` must be one of the allowed values.");
        }

        return $normalized;
    }

    private function nullableDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        $string = trim($this->requiredString($value, 'specific_date'));

        if ($string === '') {
            return null;
        }

        return (CarbonImmutable::createFromFormat('Y-m-d', $string, 'UTC')
            ?: throw new UnprocessableEntityHttpException('`specific_date` must use `Y-m-d` format.'))
            ->startOfDay();
    }

    /**
     * @param  list<string>  $allowedValues
     */
    private function nullableNormalizedEnum(mixed $value, array $allowedValues, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim($this->requiredString($value, $field));

        if ($string === '') {
            return null;
        }

        return $this->normalizedEnum($string, $allowedValues, $field);
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim($this->requiredString($value, 'notes'));

        return $string !== '' ? $string : null;
    }

    private function normalizedTime(mixed $value, string $field): string
    {
        $normalized = trim($this->requiredString($value, $field));

        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $normalized)) {
            throw new UnprocessableEntityHttpException("`{$field}` must use `HH:MM` 24-hour format.");
        }

        return $normalized;
    }

    private function providerOrFail(string $tenantId, string $providerId): ProviderData
    {
        $provider = $this->providerRepository->findInTenant($tenantId, $providerId);

        if (! $provider instanceof ProviderData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $provider;
    }

    private function requiredString(mixed $value, string $field): string
    {
        if (! is_string($value)) {
            throw new UnprocessableEntityHttpException("`{$field}` must be a string.");
        }

        return $value;
    }

    private function ruleOrFail(string $tenantId, string $providerId, string $ruleId): AvailabilityRuleData
    {
        $rule = $this->availabilityRuleRepository->findForProvider($tenantId, $providerId, $ruleId);

        if (! $rule instanceof AvailabilityRuleData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $rule;
    }

    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time, 2);
        $hours = (int) $parts[0];
        $minutes = isset($parts[1]) ? (int) $parts[1] : 0;

        return ($hours * 60) + $minutes;
    }
}
