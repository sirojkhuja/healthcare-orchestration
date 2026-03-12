<?php

namespace App\Modules\Notifications\Infrastructure\Persistence;

use App\Modules\Notifications\Application\Contracts\EmailEventRepository;
use App\Modules\Notifications\Application\Data\EmailEventData;
use App\Modules\Notifications\Application\Data\EmailEventListCriteria;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseEmailEventRepository implements EmailEventRepository
{
    #[\Override]
    public function record(string $tenantId, array $attributes): EmailEventData
    {
        $eventId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('notification_email_events')->insert([
            'id' => $eventId,
            'tenant_id' => $tenantId,
            'notification_id' => $attributes['notification_id'] ?? null,
            'source' => $attributes['source'],
            'event_type' => $attributes['event_type'],
            'recipient_email' => $attributes['recipient_email'],
            'recipient_name' => $attributes['recipient_name'] ?? null,
            'subject' => $attributes['subject'],
            'provider_key' => $attributes['provider_key'],
            'message_id' => $attributes['message_id'] ?? null,
            'error_code' => $attributes['error_code'] ?? null,
            'error_message' => $attributes['error_message'] ?? null,
            'metadata' => json_encode($attributes['metadata'] ?? [], JSON_THROW_ON_ERROR),
            'occurred_at' => $attributes['occurred_at'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row = DB::table('notification_email_events')->where('id', $eventId)->first();

        return $row instanceof stdClass
            ? $this->toData($row)
            : throw new \LogicException('Email event could not be reloaded after insert.');
    }

    #[\Override]
    public function search(string $tenantId, EmailEventListCriteria $criteria): array
    {
        $query = DB::table('notification_email_events')
            ->where('tenant_id', $tenantId);

        foreach ([
            'source' => $criteria->source,
            'event_type' => $criteria->eventType,
            'notification_id' => $criteria->notificationId,
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
                    ->whereRaw('LOWER(recipient_email) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(recipient_name, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(subject) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(provider_key) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(message_id, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(error_message, \'\')) LIKE ?', [$pattern]);
            });
        }

        /** @var list<stdClass> $rows */
        $rows = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($criteria->limit)
            ->get()
            ->all();

        return array_map(fn (stdClass $row): EmailEventData => $this->toData($row), $rows);
    }

    private function nullableDateTime(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse(is_string($value) ? $value : '');
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonObject(mixed $value): array
    {
        if (is_array($value)) {
            /** @var array<array-key, mixed> $value */
            return $this->stringKeyMap($value);
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        /** @var array<array-key, mixed> $decoded */
        return $this->stringKeyMap($decoded);
    }

    private function toData(stdClass $row): EmailEventData
    {
        return new EmailEventData(
            eventId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            notificationId: $this->nullableString($row->notification_id ?? null),
            source: $this->stringValue($row->source ?? null),
            eventType: $this->stringValue($row->event_type ?? null),
            recipientEmail: $this->stringValue($row->recipient_email ?? null),
            recipientName: $this->nullableString($row->recipient_name ?? null),
            subject: $this->stringValue($row->subject ?? null),
            providerKey: $this->stringValue($row->provider_key ?? null),
            messageId: $this->nullableString($row->message_id ?? null),
            errorCode: $this->nullableString($row->error_code ?? null),
            errorMessage: $this->nullableString($row->error_message ?? null),
            metadata: $this->jsonObject($row->metadata ?? null),
            occurredAt: $this->nullableDateTime($row->occurred_at ?? null) ?? CarbonImmutable::now(),
            createdAt: $this->nullableDateTime($row->created_at ?? null) ?? CarbonImmutable::now(),
        );
    }

    /**
     * @param  iterable<array-key, mixed>  $items
     * @return array<string, mixed>
     */
    private function stringKeyMap(iterable $items): array
    {
        $result = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($items as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
