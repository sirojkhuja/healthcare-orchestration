<?php

namespace App\Modules\Notifications\Infrastructure\Persistence;

use App\Modules\Notifications\Application\Contracts\NotificationRepository;
use App\Modules\Notifications\Application\Data\NotificationData;
use App\Modules\Notifications\Application\Data\NotificationListCriteria;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseNotificationRepository implements NotificationRepository
{
    public function __construct(
        private readonly NotificationRecordMapper $notificationRecordMapper,
    ) {}

    #[\Override]
    public function create(string $tenantId, array $attributes): NotificationData
    {
        $notificationId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('notifications')->insert([
            'id' => $notificationId,
            'tenant_id' => $tenantId,
            'template_id' => $attributes['template_id'],
            'template_code' => $attributes['template_code'],
            'template_version' => $attributes['template_version'],
            'channel' => $attributes['channel'],
            'recipient' => $this->jsonValue($attributes['recipient']),
            'recipient_value' => $attributes['recipient_value'],
            'rendered_subject' => $attributes['rendered_subject'],
            'rendered_body' => $attributes['rendered_body'],
            'variables' => $this->jsonValue($attributes['variables']),
            'metadata' => $this->jsonValue($attributes['metadata']),
            'status' => $attributes['status'],
            'attempts' => $attributes['attempts'],
            'max_attempts' => $attributes['max_attempts'],
            'provider_key' => $attributes['provider_key'],
            'provider_message_id' => $attributes['provider_message_id'],
            'last_error_code' => $attributes['last_error_code'],
            'last_error_message' => $attributes['last_error_message'],
            'queued_at' => $attributes['queued_at'],
            'sent_at' => $attributes['sent_at'],
            'failed_at' => $attributes['failed_at'],
            'canceled_at' => $attributes['canceled_at'],
            'canceled_reason' => $attributes['canceled_reason'],
            'last_attempt_at' => $attributes['last_attempt_at'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $notificationId)
            ?? throw new \LogicException('Created notification could not be reloaded.');
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $notificationId): ?NotificationData
    {
        $row = DB::table('notifications')
            ->where('tenant_id', $tenantId)
            ->where('id', $notificationId)
            ->first();

        return $row instanceof stdClass ? $this->notificationRecordMapper->toData($row) : null;
    }

    #[\Override]
    public function search(string $tenantId, NotificationListCriteria $criteria): array
    {
        $query = DB::table('notifications')
            ->where('tenant_id', $tenantId);

        foreach ([
            'status' => $criteria->status,
            'channel' => $criteria->channel,
            'template_id' => $criteria->templateId,
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
                    ->whereRaw('LOWER(template_code) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(recipient_value) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(channel) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(rendered_subject, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(rendered_body) LIKE ?', [$pattern]);
            });
        }

        /** @var list<stdClass> $rows */
        $rows = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($criteria->limit)
            ->get()
            ->all();

        return array_map(
            fn (stdClass $row): NotificationData => $this->notificationRecordMapper->toData($row),
            $rows,
        );
    }

    #[\Override]
    public function update(string $tenantId, string $notificationId, array $updates): ?NotificationData
    {
        if ($updates === []) {
            return $this->findInTenant($tenantId, $notificationId);
        }

        unset($updates['notification_id'], $updates['tenant_id']);

        foreach (['recipient', 'variables', 'metadata'] as $jsonKey) {
            if (array_key_exists($jsonKey, $updates)) {
                $updates[$jsonKey] = $this->jsonValue($updates[$jsonKey]);
            }
        }

        DB::table('notifications')
            ->where('tenant_id', $tenantId)
            ->where('id', $notificationId)
            ->update([
                ...$updates,
                'updated_at' => CarbonImmutable::now(),
            ]);

        return $this->findInTenant($tenantId, $notificationId);
    }

    private function jsonValue(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
