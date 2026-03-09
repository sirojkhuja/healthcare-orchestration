<?php

namespace App\Modules\Patient\Infrastructure\Persistence;

use App\Modules\Patient\Application\Contracts\PatientTagRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabasePatientTagRepository implements PatientTagRepository
{
    #[\Override]
    public function listForPatient(string $tenantId, string $patientId): array
    {
        /** @var list<stdClass> $rows */
        $rows = DB::table('patient_tags')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->orderBy('tag')
            ->select(['tag'])
            ->get()
            ->all();

        $tags = [];

        foreach ($rows as $row) {
            /** @var mixed $tag */
            $tag = $row->tag ?? null;

            if (is_string($tag)) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    #[\Override]
    public function replaceForPatient(string $tenantId, string $patientId, array $tags): void
    {
        DB::table('patient_tags')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->delete();

        if ($tags === []) {
            return;
        }

        $now = CarbonImmutable::now();
        $rows = [];

        foreach ($tags as $tag) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'patient_id' => $patientId,
                'tag' => $tag,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('patient_tags')->insert($rows);
    }
}
