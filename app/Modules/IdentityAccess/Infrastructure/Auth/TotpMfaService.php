<?php

namespace App\Modules\IdentityAccess\Infrastructure\Auth;

use App\Modules\IdentityAccess\Application\Contracts\MfaTotpService;
use DateTimeInterface;

final class TotpMfaService implements MfaTotpService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private const RECOVERY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    #[\Override]
    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    #[\Override]
    public function codeAt(string $secret, DateTimeInterface $moment): string
    {
        $counter = intdiv($moment->getTimestamp(), $this->periodSeconds());
        $hash = hash_hmac('sha1', $this->packCounter($counter), $this->base32Decode($secret), true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);
        $otp = $binary % (10 ** $this->digits());

        return str_pad((string) $otp, $this->digits(), '0', STR_PAD_LEFT);
    }

    #[\Override]
    public function verifyCode(string $secret, string $code, DateTimeInterface $moment): bool
    {
        $normalizedCode = preg_replace('/\D+/', '', $code);

        if (! is_string($normalizedCode) || strlen($normalizedCode) !== $this->digits()) {
            return false;
        }

        for ($offset = -$this->window(); $offset <= $this->window(); $offset++) {
            $candidateMoment = $moment->getTimestamp() + ($offset * $this->periodSeconds());

            if (hash_equals($this->codeAt($secret, new \DateTimeImmutable('@'.$candidateMoment)), $normalizedCode)) {
                return true;
            }
        }

        return false;
    }

    #[\Override]
    public function provisioningUri(string $accountLabel, string $secret): string
    {
        $issuer = $this->issuer();
        $label = rawurlencode($issuer.':'.$accountLabel);
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => $this->digits(),
            'period' => $this->periodSeconds(),
        ]);

        return 'otpauth://totp/'.$label.'?'.$query;
    }

    #[\Override]
    public function generateRecoveryCodes(): array
    {
        $codes = [];

        for ($index = 0; $index < $this->recoveryCodeCount(); $index++) {
            $codes[] = $this->generateRecoveryCode();
        }

        return $codes;
    }

    #[\Override]
    public function recoveryCodeHash(string $recoveryCode): string
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', $recoveryCode));

        return hash('sha256', $normalized);
    }

    private function base32Decode(string $secret): string
    {
        $normalized = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $bits = '';

        foreach (str_split($normalized) as $character) {
            $position = strpos(self::BASE32_ALPHABET, $character);

            if ($position === false) {
                continue;
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                continue;
            }

            $decoded .= chr(((int) bindec($chunk)) & 0xFF);
        }

        return $decoded;
    }

    private function base32Encode(string $bytes): string
    {
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';

        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::BASE32_ALPHABET[(int) bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $encoded;
    }

    private function digits(): int
    {
        return config()->integer('medflow.auth.mfa.digits', 6);
    }

    private function generateRecoveryCode(): string
    {
        $code = '';

        for ($index = 0; $index < $this->recoveryCodeLength(); $index++) {
            $code .= self::RECOVERY_ALPHABET[random_int(0, strlen(self::RECOVERY_ALPHABET) - 1)];
        }

        return substr($code, 0, 5).'-'.substr($code, 5);
    }

    private function issuer(): string
    {
        return config()->string('medflow.auth.mfa.issuer', 'MedFlow');
    }

    private function packCounter(int $counter): string
    {
        $high = intdiv($counter, 0x100000000);
        $low = $counter % 0x100000000;

        return pack('NN', $high, $low);
    }

    private function periodSeconds(): int
    {
        return config()->integer('medflow.auth.mfa.period_seconds', 30);
    }

    private function recoveryCodeCount(): int
    {
        return config()->integer('medflow.auth.mfa.recovery_codes_count', 8);
    }

    private function recoveryCodeLength(): int
    {
        return config()->integer('medflow.auth.mfa.recovery_code_length', 10);
    }

    private function window(): int
    {
        return config()->integer('medflow.auth.mfa.window', 1);
    }
}
