<?php

namespace App\Modules\Lab\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Lab\Application\Contracts\LabTestRepository;
use App\Modules\Lab\Application\Data\LabTestData;
use App\Modules\Lab\Application\Data\LabTestListCriteria;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class LabTestCatalogService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly LabTestRepository $labTestRepository,
        private readonly LabTestAttributeNormalizer $labTestAttributeNormalizer,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): LabTestData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $normalized = $this->labTestAttributeNormalizer->normalizeCreate($attributes);
        $this->assertUniqueProviderCode($tenantId, $normalized['lab_provider_key'], $normalized['code']);
        $labTest = $this->labTestRepository->create($tenantId, $normalized);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'lab_tests.created',
            objectType: 'lab_test',
            objectId: $labTest->testId,
            after: $labTest->toArray(),
        ));

        return $labTest;
    }

    public function delete(string $testId): LabTestData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $labTest = $this->testOrFail($testId);

        if (! $this->labTestRepository->delete($tenantId, $testId)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'lab_tests.deleted',
            objectType: 'lab_test',
            objectId: $labTest->testId,
            before: $labTest->toArray(),
        ));

        return $labTest;
    }

    /**
     * @return list<LabTestData>
     */
    public function list(LabTestListCriteria $criteria): array
    {
        return $this->labTestRepository->listForTenant(
            $this->tenantContext->requireTenantId(),
            $criteria,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $testId, array $attributes): LabTestData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $labTest = $this->testOrFail($testId);
        $updates = $this->labTestAttributeNormalizer->normalizePatch($labTest, $attributes);

        if ($updates === []) {
            return $labTest;
        }

        /** @psalm-suppress MixedAssignment */
        $providerKeyValue = $updates['lab_provider_key'] ?? null;
        $providerKey = is_string($providerKeyValue)
            ? $providerKeyValue
            : $labTest->labProviderKey;
        /** @psalm-suppress MixedAssignment */
        $codeValue = $updates['code'] ?? null;
        $code = is_string($codeValue)
            ? $codeValue
            : $labTest->code;

        $this->assertUniqueProviderCode(
            $tenantId,
            $providerKey,
            $code,
            $labTest->testId,
        );
        $updated = $this->labTestRepository->update($tenantId, $testId, $updates);

        if (! $updated instanceof LabTestData) {
            throw new \LogicException('Updated lab test could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'lab_tests.updated',
            objectType: 'lab_test',
            objectId: $updated->testId,
            before: $labTest->toArray(),
            after: $updated->toArray(),
        ));

        return $updated;
    }

    private function assertUniqueProviderCode(
        string $tenantId,
        string $labProviderKey,
        string $code,
        ?string $ignoreTestId = null,
    ): void {
        if ($this->labTestRepository->providerCodeExists($tenantId, $labProviderKey, $code, $ignoreTestId)) {
            throw new UnprocessableEntityHttpException('The code field must be unique for the selected lab_provider_key in the current tenant.');
        }
    }

    private function testOrFail(string $testId): LabTestData
    {
        $labTest = $this->labTestRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $testId,
        );

        if (! $labTest instanceof LabTestData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $labTest;
    }
}
