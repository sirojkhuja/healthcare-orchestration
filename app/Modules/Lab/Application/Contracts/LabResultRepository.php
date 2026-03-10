<?php

namespace App\Modules\Lab\Application\Contracts;

use App\Modules\Lab\Application\Data\LabResultData;
use Carbon\CarbonImmutable;

interface LabResultRepository
{
    /**
     * @param  array{
     *     lab_test_id: ?string,
     *     external_result_id: ?string,
     *     status: string,
     *     observed_at: CarbonImmutable,
     *     received_at: CarbonImmutable,
     *     value_type: string,
     *     value_numeric: ?string,
     *     value_text: ?string,
     *     value_boolean: ?bool,
     *     value_json: array<string, mixed>|null,
     *     unit: ?string,
     *     reference_range: ?string,
     *     abnormal_flag: ?string,
     *     notes: ?string,
     *     raw_payload: array<string, mixed>|null
     * }  $attributes
     */
    public function create(string $tenantId, string $orderId, array $attributes): LabResultData;

    public function findInOrder(string $tenantId, string $orderId, string $resultId): ?LabResultData;

    public function findInOrderByExternalId(string $tenantId, string $orderId, string $externalResultId): ?LabResultData;

    /**
     * @return list<LabResultData>
     */
    public function listForOrder(string $tenantId, string $orderId): array;

    /**
     * @param  array<string, mixed>  $updates
     */
    public function update(string $tenantId, string $orderId, string $resultId, array $updates): ?LabResultData;
}
