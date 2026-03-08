<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\IdentityAccess\Application\Commands\DeregisterDeviceCommand;
use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\IdentityAccess\Application\Contracts\DeviceRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DeregisterDeviceCommandHandler
{
    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
        private readonly DeviceRepository $deviceRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function handle(DeregisterDeviceCommand $command): void
    {
        $current = $this->authenticatedRequestContext->current();
        $deleted = $this->deviceRepository->deleteForUser($command->deviceId, $current->user->id);

        if (! $deleted) {
            throw new NotFoundHttpException('The requested device does not exist.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'auth.device_deregistered',
            objectType: 'device',
            objectId: $command->deviceId,
            metadata: [
                'actor_user_id' => $current->user->id,
                'auth_session_id' => $current->sessionId,
            ],
        ));
    }
}
