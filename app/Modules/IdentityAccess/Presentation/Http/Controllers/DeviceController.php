<?php

namespace App\Modules\IdentityAccess\Presentation\Http\Controllers;

use App\Modules\IdentityAccess\Application\Commands\DeregisterDeviceCommand;
use App\Modules\IdentityAccess\Application\Commands\RegisterDeviceCommand;
use App\Modules\IdentityAccess\Application\Handlers\DeregisterDeviceCommandHandler;
use App\Modules\IdentityAccess\Application\Handlers\ListDevicesQueryHandler;
use App\Modules\IdentityAccess\Application\Handlers\RegisterDeviceCommandHandler;
use App\Modules\IdentityAccess\Application\Queries\ListDevicesQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class DeviceController
{
    public function list(ListDevicesQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($device) => $device->toArray(),
                $handler->handle(new ListDevicesQuery),
            ),
        ]);
    }

    public function register(Request $request, RegisterDeviceCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'installation_id' => ['required', 'string', 'max:128'],
            'name' => ['required', 'string', 'max:120'],
            'platform' => ['required', 'string', Rule::in(['ios', 'android', 'web', 'desktop'])],
            'push_token' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:64'],
        ]);

        $device = $handler->handle(new RegisterDeviceCommand(
            installationId: $this->validatedString($validated, 'installation_id'),
            name: $this->validatedString($validated, 'name'),
            platform: $this->validatedString($validated, 'platform'),
            pushToken: $this->nullableValidatedString($validated, 'push_token'),
            appVersion: $this->nullableValidatedString($validated, 'app_version'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        ));

        return response()->json([
            'status' => 'device_registered',
            'data' => $device->toArray(),
        ]);
    }

    public function deregister(string $deviceId, DeregisterDeviceCommandHandler $handler): JsonResponse
    {
        $handler->handle(new DeregisterDeviceCommand($deviceId));

        return response()->json([
            'status' => 'device_deregistered',
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function validatedString(array $validated, string $key): string
    {
        /** @var mixed $value */
        $value = $validated[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function nullableValidatedString(array $validated, string $key): ?string
    {
        /** @var mixed $value */
        $value = $validated[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
