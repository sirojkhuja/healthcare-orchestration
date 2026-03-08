<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\IdentityAccess\Application\Commands\RegisterDeviceCommand;
use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\IdentityAccess\Application\Contracts\DeviceRepository;
use App\Modules\IdentityAccess\Application\Data\RegisteredDeviceData;
use Carbon\CarbonImmutable;

final class RegisterDeviceCommandHandler
{
    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
        private readonly DeviceRepository $deviceRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function handle(RegisterDeviceCommand $command): RegisteredDeviceData
    {
        $current = $this->authenticatedRequestContext->current();
        $device = $this->deviceRepository->register(
            userId: $current->user->id,
            installationId: $command->installationId,
            name: $command->name,
            platform: $command->platform,
            pushToken: $command->pushToken,
            appVersion: $command->appVersion,
            ipAddress: $command->ipAddress,
            userAgent: $command->userAgent,
            seenAt: CarbonImmutable::now(),
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'auth.device_registered',
            objectType: 'device',
            objectId: $device->deviceId,
            after: [
                'installation_id' => $device->installationId,
                'platform' => $device->platform,
                'name' => $device->name,
            ],
            metadata: [
                'actor_user_id' => $current->user->id,
                'auth_session_id' => $current->sessionId,
            ],
        ));

        return $device;
    }
}
