<?php

namespace App\Modules\Observability\Infrastructure\Persistence;

use App\Modules\Observability\Application\Contracts\JobAdministrationRepository;
use App\Modules\Observability\Application\Data\FailedJobData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class DatabaseJobAdministrationRepository implements JobAdministrationRepository
{
    #[\Override]
    public function listFailed(?string $queue, int $limit): array
    {
        $query = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit($limit);

        if ($queue !== null) {
            $query->where('queue', $queue);
        }

        /** @var list<object{id:int|string,uuid:string,connection:string,queue:string,payload:string,exception:string,failed_at:string}> $rows */
        $rows = $query->get()->all();

        return array_map(fn (object $row): FailedJobData => $this->mapFailedJob($row), $rows);
    }

    #[\Override]
    public function retryFailedJob(string $jobId): ?FailedJobData
    {
        $row = DB::table('failed_jobs')->where('id', $jobId)->first();

        if ($row === null) {
            return null;
        }

        DB::transaction(function () use ($row): void {
            DB::table('jobs')->insert([
                'queue' => $row->queue,
                'payload' => $row->payload,
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->getTimestamp(),
                'created_at' => now()->getTimestamp(),
            ]);

            DB::table('failed_jobs')->where('id', $row->id)->delete();
        });

        /** @var object{id:int|string,uuid:string,connection:string,queue:string,payload:string,exception:string,failed_at:string} $typedRow */
        $typedRow = $row;

        return $this->mapFailedJob($typedRow);
    }

    #[\Override]
    public function summary(): array
    {
        return [
            'ready_jobs' => DB::table('jobs')->whereNull('reserved_at')->count(),
            'reserved_jobs' => DB::table('jobs')->whereNotNull('reserved_at')->count(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'pending_batches' => DB::table('job_batches')->where('pending_jobs', '>', 0)->count(),
        ];
    }

    /**
     * @param  object{id:int|string,uuid:string,connection:string,queue:string,payload:string,exception:string,failed_at:string}  $row
     */
    private function mapFailedJob(object $row): FailedJobData
    {
        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($row->payload, true);
        $displayName = 'Queued Job';

        if (is_array($payload) && isset($payload['displayName']) && is_string($payload['displayName'])) {
            $displayName = $payload['displayName'];
        }

        $firstLine = trim(explode("\n", $row->exception, 2)[0]);

        if ($firstLine === '') {
            $firstLine = trim($row->exception);
        }

        return new FailedJobData(
            jobId: (string) $row->id,
            uuid: $row->uuid,
            connection: $row->connection,
            queue: $row->queue,
            displayName: $displayName,
            errorSummary: $firstLine,
            failedAt: CarbonImmutable::parse($row->failed_at),
        );
    }
}
