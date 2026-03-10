<?php

namespace App\Modules\Scheduling\Infrastructure\Persistence;

use App\Modules\Scheduling\Application\Contracts\AppointmentNoteRepository;
use App\Modules\Scheduling\Application\Data\AppointmentNoteData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseAppointmentNoteRepository implements AppointmentNoteRepository
{
    #[\Override]
    public function create(string $tenantId, string $appointmentId, array $attributes): AppointmentNoteData
    {
        $noteId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('appointment_notes')->insert([
            'id' => $noteId,
            'tenant_id' => $tenantId,
            'appointment_id' => $appointmentId,
            'author_user_id' => $attributes['author_user_id'],
            'author_name' => $attributes['author_name'],
            'author_email' => $attributes['author_email'],
            'body' => $attributes['body'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $appointmentId, $noteId)
            ?? throw new \LogicException('Created appointment note could not be reloaded.');
    }

    #[\Override]
    public function delete(string $tenantId, string $appointmentId, string $noteId): bool
    {
        return DB::table('appointment_notes')
            ->where('tenant_id', $tenantId)
            ->where('appointment_id', $appointmentId)
            ->where('id', $noteId)
            ->delete() > 0;
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $appointmentId, string $noteId): ?AppointmentNoteData
    {
        $row = DB::table('appointment_notes')
            ->where('tenant_id', $tenantId)
            ->where('appointment_id', $appointmentId)
            ->where('id', $noteId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function listForAppointment(string $tenantId, string $appointmentId): array
    {
        /** @var list<stdClass> $rows */
        $rows = DB::table('appointment_notes')
            ->where('tenant_id', $tenantId)
            ->where('appointment_id', $appointmentId)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function update(string $tenantId, string $appointmentId, string $noteId, array $updates): ?AppointmentNoteData
    {
        if ($updates === []) {
            return $this->findInTenant($tenantId, $appointmentId, $noteId);
        }

        $updates['updated_at'] = CarbonImmutable::now();

        $updated = DB::table('appointment_notes')
            ->where('tenant_id', $tenantId)
            ->where('appointment_id', $appointmentId)
            ->where('id', $noteId)
            ->update($updates);

        if ($updated === 0) {
            return $this->findInTenant($tenantId, $appointmentId, $noteId);
        }

        return $this->findInTenant($tenantId, $appointmentId, $noteId);
    }

    private function toData(stdClass $row): AppointmentNoteData
    {
        return new AppointmentNoteData(
            noteId: $this->stringValue($row->id ?? null),
            appointmentId: $this->stringValue($row->appointment_id ?? null),
            body: $this->stringValue($row->body ?? null),
            authorUserId: $this->stringValue($row->author_user_id ?? null),
            authorName: $this->stringValue($row->author_name ?? null),
            authorEmail: $this->stringValue($row->author_email ?? null),
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

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
