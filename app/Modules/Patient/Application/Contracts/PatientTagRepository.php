<?php

namespace App\Modules\Patient\Application\Contracts;

interface PatientTagRepository
{
    /**
     * @return list<string>
     */
    public function listForPatient(string $tenantId, string $patientId): array;

    /**
     * @param  list<string>  $tags
     */
    public function replaceForPatient(string $tenantId, string $patientId, array $tags): void;
}
