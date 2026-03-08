<?php

namespace App\Shared\Infrastructure\Idempotency\Persistence;

use App\Shared\Application\Contracts\IdempotencyStore;
use App\Shared\Application\Data\IdempotencyDecision;
use App\Shared\Application\Data\IdempotencyScope;
use App\Shared\Application\Data\StoredHttpResponse;
use App\Shared\Application\Exceptions\IdempotencyReplayException;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class DatabaseIdempotencyStore implements IdempotencyStore
{
    #[\Override]
    public function acquire(IdempotencyScope $scope, string $key, string $fingerprint, DateTimeInterface $expiresAt): IdempotencyDecision
    {
        try {
            /** @var IdempotencyRequestRecord $record */
            $record = IdempotencyRequestRecord::query()->create($this->recordAttributes($scope, $key, $fingerprint, $expiresAt));

            return IdempotencyDecision::execute($record->id);
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }
        }

        /** @var IdempotencyDecision $decision */
        $decision = DB::transaction(function () use ($scope, $key, $fingerprint, $expiresAt): IdempotencyDecision {
            /** @var IdempotencyRequestRecord|null $record */
            $record = IdempotencyRequestRecord::query()
                ->where('scope_hash', $scope->hash())
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->first();

            if (! $record instanceof IdempotencyRequestRecord) {
                throw new LogicException('The idempotency record could not be reloaded after a uniqueness conflict.');
            }

            if ($record->expires_at !== null && $record->expires_at->isPast()) {
                $record->fill($this->recordAttributes($scope, $key, $fingerprint, $expiresAt, $record->id));
                $record->save();

                return IdempotencyDecision::execute($record->id);
            }

            if ($record->request_fingerprint !== $fingerprint) {
                throw IdempotencyReplayException::payloadMismatch($scope->operation);
            }

            if ($record->status === IdempotencyRequestRecord::STATUS_COMPLETED) {
                return IdempotencyDecision::replay(new StoredHttpResponse(
                    status: $record->response_status ?? 200,
                    body: $record->response_body ?? '',
                    headers: $record->response_headers ?? [],
                ));
            }

            throw IdempotencyReplayException::alreadyProcessing($scope->operation);
        });

        return $decision;
    }

    #[\Override]
    public function complete(string $recordId, StoredHttpResponse $response): void
    {
        IdempotencyRequestRecord::query()
            ->whereKey($recordId)
            ->update([
                'status' => IdempotencyRequestRecord::STATUS_COMPLETED,
                'response_status' => $response->status,
                'response_headers' => $response->headers,
                'response_body' => $response->body,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    #[\Override]
    public function release(string $recordId): void
    {
        IdempotencyRequestRecord::query()->whereKey($recordId)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function recordAttributes(IdempotencyScope $scope, string $key, string $fingerprint, DateTimeInterface $expiresAt, ?string $recordId = null): array
    {
        return [
            'id' => $recordId ?? Str::uuid()->toString(),
            'scope_hash' => $scope->hash(),
            'operation' => $scope->operation,
            'tenant_id' => $scope->tenantId,
            'actor_id' => $scope->actorId,
            'idempotency_key' => $key,
            'request_fingerprint' => $fingerprint,
            'status' => IdempotencyRequestRecord::STATUS_PROCESSING,
            'response_status' => null,
            'response_headers' => null,
            'response_body' => null,
            'completed_at' => null,
            'expires_at' => $expiresAt,
            'updated_at' => now(),
        ];
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? $exception->getCode();

        if (! is_string($sqlState) && ! is_int($sqlState)) {
            return false;
        }

        return in_array((string) $sqlState, ['23000', '23505'], true);
    }
}
