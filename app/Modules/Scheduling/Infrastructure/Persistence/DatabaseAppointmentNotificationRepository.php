<?php

namespace App\Modules\Scheduling\Infrastructure\Persistence;

use App\Modules\Scheduling\Application\Contracts\AppointmentNotificationRepository;
use App\Modules\Scheduling\Application\Data\AppointmentNotificationLinkData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseAppointmentNotificationRepository implements AppointmentNotificationRepository
{
    #[\Override]
    public function create(string $tenantId, array $attributes): AppointmentNotificationLinkData
    {
        $linkId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('appointment_notifications')->insert([
            'id' => $linkId,
            'tenant_id' => $tenantId,
            'appointment_id' => $attributes['appointment_id'],
            'notification_id' => $attributes['notification_id'],
            'notification_type' => $attributes['notification_type'],
            'channel' => $attributes['channel'],
            'template_id' => $attributes['template_id'],
            'template_code' => $attributes['template_code'],
            'recipient_value' => $attributes['recipient_value'],
            'window_key' => $attributes['window_key'],
            'requested_at' => $attributes['requested_at'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findById($tenantId, $linkId)
            ?? throw new \LogicException('Created appointment notification link could not be reloaded.');
    }

    #[\Override]
    public function findReusableLink(
        string $tenantId,
        string $appointmentId,
        string $notificationType,
        string $channel,
        ?string $windowKey,
    ): ?AppointmentNotificationLinkData {
        $query = DB::table('appointment_notifications')
            ->join('notifications', function (JoinClause $join): void {
                $join->on('notifications.id', '=', 'appointment_notifications.notification_id')
                    ->on('notifications.tenant_id', '=', 'appointment_notifications.tenant_id');
            })
            ->where('appointment_notifications.tenant_id', $tenantId)
            ->where('appointment_notifications.appointment_id', $appointmentId)
            ->where('appointment_notifications.notification_type', $notificationType)
            ->where('appointment_notifications.channel', $channel)
            ->whereIn('notifications.status', ['queued', 'sent', 'failed'])
            ->select('appointment_notifications.*')
            ->orderByDesc('appointment_notifications.created_at')
            ->orderByDesc('appointment_notifications.id');

        if ($windowKey === null) {
            $query->whereNull('appointment_notifications.window_key');
        } else {
            $query->where('appointment_notifications.window_key', $windowKey);
        }

        $row = $query->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    private function findById(string $tenantId, string $linkId): ?AppointmentNotificationLinkData
    {
        $row = DB::table('appointment_notifications')
            ->where('tenant_id', $tenantId)
            ->where('id', $linkId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    private function toData(stdClass $row): AppointmentNotificationLinkData
    {
        return new AppointmentNotificationLinkData(
            appointmentNotificationId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            appointmentId: $this->stringValue($row->appointment_id ?? null),
            notificationId: $this->stringValue($row->notification_id ?? null),
            notificationType: $this->stringValue($row->notification_type ?? null),
            channel: $this->stringValue($row->channel ?? null),
            templateId: $this->stringValue($row->template_id ?? null),
            templateCode: $this->stringValue($row->template_code ?? null),
            recipientValue: $this->stringValue($row->recipient_value ?? null),
            windowKey: $this->nullableString($row->window_key ?? null),
            requestedAt: $this->dateTime($row->requested_at ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
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
}
