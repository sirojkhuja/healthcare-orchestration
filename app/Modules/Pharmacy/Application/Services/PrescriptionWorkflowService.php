<?php

namespace App\Modules\Pharmacy\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Pharmacy\Application\Contracts\PrescriptionRepository;
use App\Modules\Pharmacy\Application\Data\PrescriptionData;
use App\Modules\Pharmacy\Domain\Prescriptions\InvalidPrescriptionTransition;
use App\Modules\Pharmacy\Domain\Prescriptions\Prescription;
use App\Modules\Pharmacy\Domain\Prescriptions\PrescriptionActor;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PrescriptionWorkflowService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PrescriptionRepository $prescriptionRepository,
        private readonly PrescriptionAggregateMapper $prescriptionAggregateMapper,
        private readonly PrescriptionActorContext $prescriptionActorContext,
        private readonly AuditTrailWriter $auditTrailWriter,
        private readonly PrescriptionOutboxPublisher $prescriptionOutboxPublisher,
    ) {}

    public function cancel(string $prescriptionId, string $reason): PrescriptionData
    {
        return $this->transition(
            prescriptionId: $prescriptionId,
            auditAction: 'prescriptions.canceled',
            eventType: 'prescription.canceled',
            mutator: static function (Prescription $prescription, CarbonImmutable $occurredAt, PrescriptionActor $actor) use ($reason): void {
                $prescription->cancel($occurredAt->toDateTimeImmutable(), $actor, $reason);
            },
        );
    }

    public function dispense(string $prescriptionId): PrescriptionData
    {
        return $this->transition(
            prescriptionId: $prescriptionId,
            auditAction: 'prescriptions.dispensed',
            eventType: 'prescription.dispensed',
            mutator: static function (Prescription $prescription, CarbonImmutable $occurredAt, PrescriptionActor $actor): void {
                $prescription->dispense($occurredAt->toDateTimeImmutable(), $actor);
            },
        );
    }

    public function issue(string $prescriptionId): PrescriptionData
    {
        return $this->transition(
            prescriptionId: $prescriptionId,
            auditAction: 'prescriptions.issued',
            eventType: 'prescription.issued',
            mutator: static function (Prescription $prescription, CarbonImmutable $occurredAt, PrescriptionActor $actor): void {
                $prescription->issue($occurredAt->toDateTimeImmutable(), $actor);
            },
        );
    }

    /**
     * @param  callable(Prescription, CarbonImmutable, PrescriptionActor): void  $mutator
     */
    private function transition(string $prescriptionId, string $auditAction, string $eventType, callable $mutator): PrescriptionData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $before = $this->prescriptionOrFail($tenantId, $prescriptionId);
        $actor = $this->prescriptionActorContext->current();
        $occurredAt = CarbonImmutable::now();

        /** @var PrescriptionData $updated */
        $updated = DB::transaction(function () use ($tenantId, $before, $actor, $occurredAt, $mutator): PrescriptionData {
            $aggregate = $this->prescriptionAggregateMapper->fromData($before);

            try {
                $mutator($aggregate, $occurredAt, $actor);
            } catch (InvalidPrescriptionTransition $exception) {
                throw new ConflictHttpException($exception->getMessage(), $exception);
            }

            $snapshot = $aggregate->snapshot();
            $updated = $this->prescriptionRepository->update($tenantId, $before->prescriptionId, $snapshot);

            if (! $updated instanceof PrescriptionData) {
                throw new LogicException('Updated prescription could not be reloaded.');
            }

            return $updated;
        });

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: $auditAction,
            objectType: 'prescription',
            objectId: $updated->prescriptionId,
            before: $before->toArray(),
            after: $updated->toArray(),
        ));
        $this->prescriptionOutboxPublisher->publishPrescriptionEvent($eventType, $updated);

        return $updated;
    }

    private function prescriptionOrFail(string $tenantId, string $prescriptionId): PrescriptionData
    {
        $prescription = $this->prescriptionRepository->findInTenant($tenantId, $prescriptionId);

        if (! $prescription instanceof PrescriptionData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $prescription;
    }
}
