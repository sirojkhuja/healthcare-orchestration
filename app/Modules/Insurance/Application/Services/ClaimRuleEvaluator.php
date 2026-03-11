<?php

namespace App\Modules\Insurance\Application\Services;

use App\Modules\Insurance\Application\Contracts\InsuranceRuleRepository;
use App\Modules\Insurance\Application\Contracts\PatientInsurancePolicyRepository;
use App\Modules\Insurance\Application\Data\ClaimData;
use App\Modules\Insurance\Application\Data\InsuranceRuleData;
use App\Modules\Insurance\Application\Data\PatientInsurancePolicyData;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use LogicException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ClaimRuleEvaluator
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly InsuranceRuleRepository $insuranceRuleRepository,
        private readonly PatientInsurancePolicyRepository $patientInsurancePolicyRepository,
    ) {}

    public function assertCanSubmit(ClaimData $claim): void
    {
        $violations = [];

        foreach ($this->applicableRules($claim) as $rule) {
            if ($rule->requiresAttachment && $claim->attachmentCount < 1) {
                $violations[] = sprintf('Rule %s requires at least one claim attachment.', $rule->code);
            }

            if ($rule->requiresPrimaryPolicy) {
                $policy = $this->currentPolicy($claim);

                if (! $policy instanceof PatientInsurancePolicyData || ! $policy->isPrimary) {
                    $violations[] = sprintf('Rule %s requires a current primary patient policy.', $rule->code);
                }
            }

            if ($rule->maxClaimAmount !== null && $this->compareDecimals($claim->billedAmount, $rule->maxClaimAmount) === 1) {
                $violations[] = sprintf('Rule %s limits billed amount to %s.', $rule->code, $rule->maxClaimAmount);
            }

            if ($rule->submissionWindowDays !== null) {
                $earliestAllowed = CarbonImmutable::now()->startOfDay()->subDays($rule->submissionWindowDays);

                if ($claim->serviceDate->lt($earliestAllowed)) {
                    $violations[] = sprintf(
                        'Rule %s requires submission within %d days of the service date.',
                        $rule->code,
                        $rule->submissionWindowDays,
                    );
                }
            }
        }

        if ($violations !== []) {
            throw new UnprocessableEntityHttpException(implode(' ', $violations));
        }
    }

    /**
     * @return list<InsuranceRuleData>
     */
    private function applicableRules(ClaimData $claim): array
    {
        $rules = $this->insuranceRuleRepository->listActiveForPayer(
            $this->tenantContext->requireTenantId(),
            $claim->payerId,
        );

        return array_values(array_filter(
            $rules,
            fn (InsuranceRuleData $rule): bool => $rule->serviceCategory === null
                || in_array($rule->serviceCategory, $claim->serviceCategories, true),
        ));
    }

    private function currentPolicy(ClaimData $claim): ?PatientInsurancePolicyData
    {
        if ($claim->patientPolicyId === null) {
            return null;
        }

        return $this->patientInsurancePolicyRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $claim->patientId,
            $claim->patientPolicyId,
        );
    }

    private function compareDecimals(string $left, string $right): int
    {
        /** @psalm-suppress ArgumentTypeCoercion */
        /** @phpstan-ignore-next-line */
        return bccomp($this->normalizeDecimal($left), $this->normalizeDecimal($right), 2);
    }

    private function normalizeDecimal(string $value): string
    {
        if (! preg_match('/^\d{1,10}(\.\d{1,2})?$/', $value)) {
            throw new LogicException('Claim amounts must be stored as valid decimal strings.');
        }

        return number_format((float) $value, 2, '.', '');
    }
}
