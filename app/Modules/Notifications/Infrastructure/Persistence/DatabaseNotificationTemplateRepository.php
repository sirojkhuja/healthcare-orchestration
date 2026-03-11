<?php

namespace App\Modules\Notifications\Infrastructure\Persistence;

use App\Modules\Notifications\Application\Contracts\NotificationTemplateRepository;
use App\Modules\Notifications\Application\Data\NotificationTemplateData;
use App\Modules\Notifications\Application\Data\NotificationTemplateListCriteria;
use App\Modules\Notifications\Application\Data\NotificationTemplateVersionData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseNotificationTemplateRepository implements NotificationTemplateRepository
{
    #[\Override]
    public function codeExists(string $tenantId, string $code, ?string $ignoreTemplateId = null): bool
    {
        $query = DB::table('notification_templates')
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->whereNull('deleted_at');

        if ($ignoreTemplateId !== null) {
            $query->where('id', '!=', $ignoreTemplateId);
        }

        return $query->exists();
    }

    #[\Override]
    public function create(string $tenantId, array $attributes): NotificationTemplateData
    {
        $templateId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::transaction(function () use ($tenantId, $templateId, $attributes, $now): void {
            DB::table('notification_templates')->insert([
                'id' => $templateId,
                'tenant_id' => $tenantId,
                'code' => $attributes['code'],
                'name' => $attributes['name'],
                'channel' => $attributes['channel'],
                'description' => $attributes['description'],
                'is_active' => $attributes['is_active'],
                'current_version' => 1,
                'subject_template' => $attributes['subject_template'],
                'body_template' => $attributes['body_template'],
                'placeholders' => $this->jsonValue($attributes['placeholders']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->insertVersion($tenantId, $templateId, 1, $attributes, $now);
        });

        return $this->findInTenant($tenantId, $templateId)
            ?? throw new \LogicException('Created notification template could not be reloaded.');
    }

    #[\Override]
    public function delete(string $tenantId, string $templateId): bool
    {
        $deletedAt = CarbonImmutable::now();

        return DB::table('notification_templates')
            ->where('tenant_id', $tenantId)
            ->where('id', $templateId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
            ]) > 0;
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $templateId): ?NotificationTemplateData
    {
        $row = $this->baseTemplateQuery($tenantId)
            ->where('id', $templateId)
            ->first();

        return $row instanceof stdClass ? $this->toTemplateData($row) : null;
    }

    #[\Override]
    public function listForTenant(string $tenantId, NotificationTemplateListCriteria $criteria): array
    {
        $query = $this->baseTemplateQuery($tenantId);

        if ($criteria->channel !== null && trim($criteria->channel) !== '') {
            $query->whereRaw('LOWER(channel) = ?', [mb_strtolower(trim($criteria->channel))]);
        }

        if ($criteria->isActive !== null) {
            $query->where('is_active', $criteria->isActive);
        }

        if ($criteria->query !== null && trim($criteria->query) !== '') {
            $pattern = '%'.mb_strtolower(trim($criteria->query)).'%';
            $query->where(function (Builder $builder) use ($pattern): void {
                $builder
                    ->whereRaw('LOWER(code) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(channel) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(subject_template, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(body_template) LIKE ?', [$pattern]);
            });
        }

        /** @var list<stdClass> $rows */
        $rows = $query
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->orderBy('code')
            ->orderBy('updated_at')
            ->limit($criteria->limit)
            ->get()
            ->all();

        return array_map($this->toTemplateData(...), $rows);
    }

    #[\Override]
    public function listVersions(string $tenantId, string $templateId): array
    {
        /** @var list<stdClass> $rows */
        $rows = DB::table('notification_template_versions')
            ->where('tenant_id', $tenantId)
            ->where('template_id', $templateId)
            ->orderByDesc('version')
            ->get()
            ->all();

        return array_map($this->toVersionData(...), $rows);
    }

    #[\Override]
    public function update(string $tenantId, string $templateId, array $attributes): ?NotificationTemplateData
    {
        if ($attributes === []) {
            return $this->findInTenant($tenantId, $templateId);
        }

        /** @var NotificationTemplateData|null $updated */
        $updated = DB::transaction(function () use ($tenantId, $templateId, $attributes): ?NotificationTemplateData {
            $row = DB::table('notification_templates')
                ->where('tenant_id', $tenantId)
                ->where('id', $templateId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (! $row instanceof stdClass) {
                return null;
            }

            $now = CarbonImmutable::now();
            $nextVersion = $this->intValue($row->current_version ?? null) + 1;
            $versionAttributes = [
                'code' => $this->stringValue($attributes['code'] ?? $row->code ?? null),
                'name' => $this->stringValue($attributes['name'] ?? $row->name ?? null),
                'channel' => $this->stringValue($attributes['channel'] ?? $row->channel ?? null),
                'description' => $this->nullableString($attributes['description'] ?? $row->description ?? null),
                'is_active' => array_key_exists('is_active', $attributes)
                    ? (bool) $attributes['is_active']
                    : (bool) ($row->is_active ?? false),
                'subject_template' => array_key_exists('subject_template', $attributes)
                    ? $this->nullableString($attributes['subject_template'])
                    : $this->nullableString($row->subject_template ?? null),
                'body_template' => $this->stringValue($attributes['body_template'] ?? $row->body_template ?? null),
                'placeholders' => array_key_exists('placeholders', $attributes)
                    ? $this->placeholdersValue($attributes['placeholders'])
                    : $this->jsonArray($row->placeholders ?? null),
            ];

            DB::table('notification_templates')
                ->where('tenant_id', $tenantId)
                ->where('id', $templateId)
                ->whereNull('deleted_at')
                ->update([
                    ...$attributes,
                    'placeholders' => array_key_exists('placeholders', $attributes)
                        ? $this->jsonValue($attributes['placeholders'])
                        : $row->placeholders,
                    'current_version' => $nextVersion,
                    'updated_at' => $now,
                ]);

            $this->insertVersion($tenantId, $templateId, $nextVersion, $versionAttributes, $now);

            return $this->findInTenant($tenantId, $templateId);
        });

        return $updated;
    }

    private function baseTemplateQuery(string $tenantId): Builder
    {
        return DB::table('notification_templates')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->select([
                'id',
                'tenant_id',
                'code',
                'name',
                'channel',
                'description',
                'is_active',
                'current_version',
                'subject_template',
                'body_template',
                'placeholders',
                'deleted_at',
                'created_at',
                'updated_at',
            ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertVersion(
        string $tenantId,
        string $templateId,
        int $version,
        array $attributes,
        CarbonImmutable $now,
    ): void {
        DB::table('notification_template_versions')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'template_id' => $templateId,
            'version' => $version,
            'code' => $attributes['code'],
            'name' => $attributes['name'],
            'channel' => $attributes['channel'],
            'description' => $this->nullableString($attributes['description'] ?? null),
            'is_active' => (bool) ($attributes['is_active'] ?? false),
            'subject_template' => $this->nullableString($attributes['subject_template'] ?? null),
            'body_template' => $this->stringValue($attributes['body_template'] ?? null),
            'placeholders' => $this->jsonValue($this->placeholdersValue($attributes['placeholders'] ?? [])),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @return list<string>
     */
    private function jsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $this->stringList($value);
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $this->stringList($decoded) : [];
    }

    /**
     * @return list<string>
     */
    private function placeholdersValue(mixed $value): array
    {
        return is_array($value) ? $this->stringList($value) : [];
    }

    private function jsonValue(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function nullableDateTime(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return $this->dateTime($value);
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
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : 0);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<string>
     */
    private function stringList(array $values): array
    {
        $strings = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($values as $value) {
            if (is_string($value)) {
                $strings[] = $value;
            }
        }

        return $strings;
    }

    private function toTemplateData(stdClass $row): NotificationTemplateData
    {
        return new NotificationTemplateData(
            templateId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            code: $this->stringValue($row->code ?? null),
            name: $this->stringValue($row->name ?? null),
            channel: $this->stringValue($row->channel ?? null),
            description: $this->nullableString($row->description ?? null),
            isActive: (bool) ($row->is_active ?? false),
            currentVersion: $this->intValue($row->current_version ?? null),
            subjectTemplate: $this->nullableString($row->subject_template ?? null),
            bodyTemplate: $this->stringValue($row->body_template ?? null),
            placeholders: $this->jsonArray($row->placeholders ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
            deletedAt: $this->nullableDateTime($row->deleted_at ?? null),
        );
    }

    private function toVersionData(stdClass $row): NotificationTemplateVersionData
    {
        return new NotificationTemplateVersionData(
            version: $this->intValue($row->version ?? null),
            code: $this->stringValue($row->code ?? null),
            name: $this->stringValue($row->name ?? null),
            channel: $this->stringValue($row->channel ?? null),
            description: $this->nullableString($row->description ?? null),
            isActive: (bool) ($row->is_active ?? false),
            subjectTemplate: $this->nullableString($row->subject_template ?? null),
            bodyTemplate: $this->stringValue($row->body_template ?? null),
            placeholders: $this->jsonArray($row->placeholders ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }
}
