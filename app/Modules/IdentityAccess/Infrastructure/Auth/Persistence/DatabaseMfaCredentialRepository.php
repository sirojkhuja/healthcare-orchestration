<?php

namespace App\Modules\IdentityAccess\Infrastructure\Auth\Persistence;

use App\Modules\IdentityAccess\Application\Contracts\MfaCredentialRepository;
use App\Modules\IdentityAccess\Application\Data\MfaCredentialData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;

final class DatabaseMfaCredentialRepository implements MfaCredentialRepository
{
    #[\Override]
    public function upsertPending(
        string $userId,
        string $secret,
        array $recoveryCodeHashes,
        DateTimeInterface $now,
    ): MfaCredentialData {
        $record = MfaCredentialRecord::query()->firstOrNew([
            'user_id' => $userId,
        ]);

        if (! $record->exists) {
            $record->setAttribute('id', (string) Str::uuid());
            $record->setAttribute('created_at', $now);
        }

        $record->fill([
            'secret' => $secret,
            'recovery_code_hashes' => $recoveryCodeHashes,
            'enabled_at' => null,
            'last_used_at' => null,
            'disabled_at' => null,
            'updated_at' => $now,
        ]);
        $record->save();

        return $this->toData($record);
    }

    #[\Override]
    public function findForUser(string $userId): ?MfaCredentialData
    {
        /** @var MfaCredentialRecord|null $record */
        $record = MfaCredentialRecord::query()->where('user_id', $userId)->first();

        return $record instanceof MfaCredentialRecord ? $this->toData($record) : null;
    }

    #[\Override]
    public function findEnabledForUser(string $userId): ?MfaCredentialData
    {
        /** @var MfaCredentialRecord|null $record */
        $record = MfaCredentialRecord::query()
            ->where('user_id', $userId)
            ->whereNotNull('enabled_at')
            ->whereNull('disabled_at')
            ->first();

        return $record instanceof MfaCredentialRecord ? $this->toData($record) : null;
    }

    #[\Override]
    public function enable(string $credentialId, DateTimeInterface $enabledAt): ?MfaCredentialData
    {
        $updated = MfaCredentialRecord::query()
            ->whereKey($credentialId)
            ->whereNull('disabled_at')
            ->update([
                'enabled_at' => $enabledAt,
                'updated_at' => CarbonImmutable::now(),
            ]);

        if ($updated < 1) {
            return null;
        }

        $record = MfaCredentialRecord::query()->find($credentialId);

        return $record instanceof MfaCredentialRecord ? $this->toData($record) : null;
    }

    #[\Override]
    public function disable(string $credentialId, DateTimeInterface $disabledAt): bool
    {
        $updated = MfaCredentialRecord::query()
            ->whereKey($credentialId)
            ->whereNull('disabled_at')
            ->update([
                'secret' => null,
                'recovery_code_hashes' => [],
                'enabled_at' => null,
                'disabled_at' => $disabledAt,
                'updated_at' => CarbonImmutable::now(),
            ]);

        return $updated > 0;
    }

    #[\Override]
    public function consumeRecoveryCode(string $credentialId, string $recoveryCodeHash, DateTimeInterface $usedAt): bool
    {
        /** @var MfaCredentialRecord|null $record */
        $record = MfaCredentialRecord::query()->find($credentialId);
        $disabledAt = $record?->getAttribute('disabled_at');

        if (! $record instanceof MfaCredentialRecord || $disabledAt !== null) {
            return false;
        }

        $hashes = $this->recoveryCodeHashes($record->getAttribute('recovery_code_hashes'));
        $remaining = [];
        $matched = false;

        foreach ($hashes as $hash) {
            if (! $matched && hash_equals($hash, $recoveryCodeHash)) {
                $matched = true;

                continue;
            }

            $remaining[] = $hash;
        }

        if (! $matched) {
            return false;
        }

        $record->forceFill([
            'recovery_code_hashes' => $remaining,
            'last_used_at' => $usedAt,
            'updated_at' => CarbonImmutable::now(),
        ])->save();

        return true;
    }

    #[\Override]
    public function touchLastUsed(string $credentialId, DateTimeInterface $usedAt): void
    {
        MfaCredentialRecord::query()
            ->whereKey($credentialId)
            ->whereNull('disabled_at')
            ->update([
                'last_used_at' => $usedAt,
                'updated_at' => CarbonImmutable::now(),
            ]);
    }

    private function toData(MfaCredentialRecord $record): MfaCredentialData
    {
        $enabledAt = $this->nullableDateTime($record->getAttribute('enabled_at'));
        $lastUsedAt = $this->nullableDateTime($record->getAttribute('last_used_at'));
        $disabledAt = $this->nullableDateTime($record->getAttribute('disabled_at'));
        $createdAt = $this->dateTime($record->getAttribute('created_at'));

        return new MfaCredentialData(
            credentialId: $this->stringValue($record->getAttribute('id')),
            userId: $this->stringValue($record->getAttribute('user_id')),
            secret: $this->nullableString($record->getAttribute('secret')),
            recoveryCodeHashes: $this->recoveryCodeHashes($record->getAttribute('recovery_code_hashes')),
            enabledAt: $enabledAt,
            lastUsedAt: $lastUsedAt,
            disabledAt: $disabledAt,
            createdAt: $createdAt,
        );
    }

    /**
     * @return list<string>
     */
    private function recoveryCodeHashes(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $hashes = array_values(array_filter(
            $value,
            static fn (mixed $item): bool => is_string($item) && $item !== '',
        ));

        /** @var list<string> $hashes */
        return $hashes;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function nullableDateTime(mixed $value): ?DateTimeInterface
    {
        return $value instanceof DateTimeInterface ? $value : null;
    }

    private function dateTime(mixed $value): DateTimeInterface
    {
        return $value instanceof DateTimeInterface ? $value : CarbonImmutable::now();
    }
}
