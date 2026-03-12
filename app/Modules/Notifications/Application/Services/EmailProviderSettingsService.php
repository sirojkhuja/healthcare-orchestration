<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Application\Contracts\EmailProviderSettingsRepository;
use App\Modules\Notifications\Application\Data\EmailProviderSettingsData;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class EmailProviderSettingsService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EmailProviderSettingsRepository $emailProviderSettingsRepository,
    ) {}

    public function get(): EmailProviderSettingsData
    {
        return $this->emailProviderSettingsRepository->get($this->tenantContext->requireTenantId());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes): EmailProviderSettingsData
    {
        $fromName = $this->requiredString($attributes, 'from_name');
        $replyToAddress = $this->nullableEmail($attributes['reply_to_address'] ?? null, 'reply_to_address');
        $replyToName = $this->nullableString($attributes['reply_to_name'] ?? null);

        return $this->emailProviderSettingsRepository->save($this->tenantContext->requireTenantId(), [
            'enabled' => $this->requiredBool($attributes['enabled'] ?? null),
            'from_address' => $this->requiredEmail($attributes['from_address'] ?? null, 'from_address'),
            'from_name' => $fromName,
            'reply_to_address' => $replyToAddress,
            'reply_to_name' => $replyToAddress === null ? null : ($replyToName ?? $fromName),
        ]);
    }

    private function requiredBool(mixed $value): bool
    {
        if (! is_bool($value)) {
            throw new UnprocessableEntityHttpException('The enabled field is required.');
        }

        return $value;
    }

    private function requiredEmail(mixed $value, string $field): string
    {
        $email = $this->nullableEmail($value, $field);

        if ($email === null) {
            throw new UnprocessableEntityHttpException(sprintf('The %s field is required.', $field));
        }

        return $email;
    }

    private function nullableEmail(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new UnprocessableEntityHttpException(sprintf('The %s field must be a valid email address.', $field));
        }

        $email = mb_strtolower(trim($value));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new UnprocessableEntityHttpException(sprintf('The %s field must be a valid email address.', $field));
        }

        return $email;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function requiredString(array $attributes, string $field): string
    {
        if (! array_key_exists($field, $attributes) || ! is_string($attributes[$field]) || trim($attributes[$field]) === '') {
            throw new UnprocessableEntityHttpException(sprintf('The %s field is required.', $field));
        }

        return trim($attributes[$field]);
    }
}
