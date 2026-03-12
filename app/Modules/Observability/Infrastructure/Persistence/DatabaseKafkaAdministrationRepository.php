<?php

namespace App\Modules\Observability\Infrastructure\Persistence;

use App\Modules\Observability\Application\Contracts\KafkaAdministrationRepository;
use App\Modules\Observability\Application\Data\KafkaConsumerLagData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class DatabaseKafkaAdministrationRepository implements KafkaAdministrationRepository
{
    #[\Override]
    public function clearReplayReceipts(string $consumerName, ?array $messageIds, ?CarbonImmutable $processedBefore, int $limit): int
    {
        $query = DB::table('kafka_consumer_receipts')
            ->where('consumer_name', $consumerName)
            ->orderBy('processed_at')
            ->limit($limit);

        if ($messageIds !== null && $messageIds !== []) {
            $query->whereIn('message_id', $messageIds);
        }

        if ($processedBefore instanceof CarbonImmutable) {
            $query->where('processed_at', '<=', $processedBefore);
        }

        $receiptIds = array_values(array_filter(
            $query->pluck('id')->all(),
            static fn (mixed $id): bool => is_scalar($id),
        ));

        /** @var list<string> $ids */
        $ids = array_map(
            static fn (string|int|float|bool $id): string => (string) $id,
            $receiptIds,
        );

        if ($ids === []) {
            return 0;
        }

        return DB::table('kafka_consumer_receipts')->whereIn('id', $ids)->delete();
    }

    #[\Override]
    public function listLag(): array
    {
        /** @var list<object{consumer_name:string,topic:string,processed_total:int,last_processed_at:?string}> $rows */
        $rows = DB::table('kafka_consumer_receipts')
            ->select([
                'consumer_name',
                'topic',
                DB::raw('COUNT(*) as processed_total'),
                DB::raw('MAX(processed_at) as last_processed_at'),
            ])
            ->groupBy('consumer_name', 'topic')
            ->orderBy('consumer_name')
            ->orderBy('topic')
            ->get()
            ->all();

        $aggregated = [];
        $now = CarbonImmutable::now();

        foreach ($rows as $row) {
            $lastProcessedAt = is_string($row->last_processed_at)
                ? CarbonImmutable::parse($row->last_processed_at)
                : null;

            if (! array_key_exists($row->consumer_name, $aggregated)) {
                $aggregated[$row->consumer_name] = [
                    'topics' => [],
                    'processed_total' => 0,
                    'last_processed_at' => $lastProcessedAt,
                ];
            }

            $aggregated[$row->consumer_name]['topics'][] = $row->topic;
            $aggregated[$row->consumer_name]['processed_total'] += $row->processed_total;

            $currentLast = $aggregated[$row->consumer_name]['last_processed_at'];

            if (! $currentLast instanceof CarbonImmutable || ($lastProcessedAt instanceof CarbonImmutable && $lastProcessedAt->gt($currentLast))) {
                $aggregated[$row->consumer_name]['last_processed_at'] = $lastProcessedAt;
            }
        }

        $consumers = [];

        foreach ($aggregated as $consumerName => $data) {
            $lastProcessedAt = $data['last_processed_at'];
            $lagSeconds = $lastProcessedAt instanceof CarbonImmutable
                ? (int) $now->diffInSeconds($lastProcessedAt)
                : 0;

            $consumers[] = new KafkaConsumerLagData(
                consumerName: $consumerName,
                topics: array_values(array_unique($data['topics'])),
                processedTotal: $data['processed_total'],
                lastProcessedAt: $lastProcessedAt,
                receiptLagSeconds: $lagSeconds,
            );
        }

        return $consumers;
    }
}
