<?php

namespace App\Modules\IdentityAccess\Presentation\Http\Controllers;

use App\Modules\IdentityAccess\Application\Commands\RevokeAllSessionsCommand;
use App\Modules\IdentityAccess\Application\Commands\RevokeSessionCommand;
use App\Modules\IdentityAccess\Application\Commands\UpdateIpAllowlistCommand;
use App\Modules\IdentityAccess\Application\Handlers\GetIpAllowlistQueryHandler;
use App\Modules\IdentityAccess\Application\Handlers\ListSessionsQueryHandler;
use App\Modules\IdentityAccess\Application\Handlers\RevokeAllSessionsCommandHandler;
use App\Modules\IdentityAccess\Application\Handlers\RevokeSessionCommandHandler;
use App\Modules\IdentityAccess\Application\Handlers\UpdateIpAllowlistCommandHandler;
use App\Modules\IdentityAccess\Application\Queries\GetIpAllowlistQuery;
use App\Modules\IdentityAccess\Application\Queries\ListSessionsQuery;
use App\Modules\IdentityAccess\Infrastructure\Security\CidrMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SecurityController
{
    public function listSessions(ListSessionsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($session) => $session->toArray(),
                $handler->handle(new ListSessionsQuery),
            ),
        ]);
    }

    public function revokeSession(string $sessionId, RevokeSessionCommandHandler $handler): JsonResponse
    {
        $handler->handle(new RevokeSessionCommand($sessionId));

        return response()->json([
            'status' => 'session_revoked',
        ]);
    }

    public function revokeAllSessions(RevokeAllSessionsCommandHandler $handler): JsonResponse
    {
        return response()->json([
            'status' => 'all_sessions_revoked',
            'revoked_sessions' => $handler->handle(new RevokeAllSessionsCommand),
        ]);
    }

    public function getIpAllowlist(GetIpAllowlistQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($entry) => $entry->toArray(),
                $handler->handle(new GetIpAllowlistQuery),
            ),
        ]);
    }

    public function updateIpAllowlist(Request $request, UpdateIpAllowlistCommandHandler $handler, CidrMatcher $cidrMatcher): JsonResponse
    {
        $validated = $request->validate([
            'entries' => ['required', 'array'],
            'entries.*.cidr' => [
                'required',
                'string',
                'distinct',
                function (string $attribute, mixed $value, \Closure $fail) use ($cidrMatcher): void {
                    if (! is_string($value) || ! $cidrMatcher->isValid($value)) {
                        $fail('The CIDR entry must be valid IPv4 or IPv6 CIDR notation.');
                    }
                },
            ],
            'entries.*.label' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var mixed $entriesInput */
        $entriesInput = $validated['entries'] ?? [];
        $entries = is_array($entriesInput) ? $this->normalizedAllowlistEntries($entriesInput) : [];

        return response()->json([
            'status' => 'ip_allowlist_updated',
            'data' => array_map(
                static fn ($entry) => $entry->toArray(),
                $handler->handle(new UpdateIpAllowlistCommand($entries)),
            ),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $entriesInput
     * @return list<array{cidr: string, label: string|null}>
     */
    private function normalizedAllowlistEntries(array $entriesInput): array
    {
        /** @var list<array{cidr: string, label: string|null}> $entries */
        $entries = [];

        foreach ($entriesInput as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            /** @var mixed $cidrValue */
            $cidrValue = $entry['cidr'] ?? '';
            /** @var mixed $labelValue */
            $labelValue = $entry['label'] ?? null;

            $entries[] = [
                'cidr' => is_string($cidrValue) ? strtolower($cidrValue) : '',
                'label' => is_string($labelValue) && $labelValue !== '' ? $labelValue : null,
            ];
        }

        return $entries;
    }
}
