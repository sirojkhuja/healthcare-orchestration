<?php

namespace App\Modules\IdentityAccess\Infrastructure\Security;

final class CidrMatcher
{
    public function isValid(string $cidr): bool
    {
        [$network, $prefixLength] = $this->split($cidr) ?? [null, null];

        if (! is_string($network) || ! is_int($prefixLength)) {
            return false;
        }

        $networkBinary = @inet_pton($network);

        if (! is_string($networkBinary)) {
            return false;
        }

        $maxPrefixLength = strlen($networkBinary) === 4 ? 32 : 128;

        return $prefixLength >= 0 && $prefixLength <= $maxPrefixLength;
    }

    public function matches(string $ipAddress, string $cidr): bool
    {
        [$network, $prefixLength] = $this->split($cidr) ?? [null, null];

        if (! is_string($network) || ! is_int($prefixLength)) {
            return false;
        }

        $ipBinary = @inet_pton($ipAddress);
        $networkBinary = @inet_pton($network);

        if (! is_string($ipBinary) || ! is_string($networkBinary) || strlen($ipBinary) !== strlen($networkBinary)) {
            return false;
        }

        $fullBytes = intdiv($prefixLength, 8);
        $remainingBits = $prefixLength % 8;

        if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($networkBinary, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (~((1 << (8 - $remainingBits)) - 1)) & 0xFF;

        return (ord($ipBinary[$fullBytes]) & $mask) === (ord($networkBinary[$fullBytes]) & $mask);
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    private function split(string $cidr): ?array
    {
        $parts = explode('/', $cidr, 2);

        if (count($parts) !== 2 || ! ctype_digit($parts[1])) {
            return null;
        }

        return [$parts[0], (int) $parts[1]];
    }
}
