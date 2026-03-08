<?php

namespace App\Modules\IdentityAccess\Presentation\Http\Controllers;

use App\Modules\IdentityAccess\Application\Commands\UpdateMyProfileCommand;
use App\Modules\IdentityAccess\Application\Commands\UpdateProfileCommand;
use App\Modules\IdentityAccess\Application\Commands\UploadMyAvatarCommand;
use App\Modules\IdentityAccess\Application\Data\ProfilePatchData;
use App\Modules\IdentityAccess\Application\Handlers\GetMyProfileQueryHandler;
use App\Modules\IdentityAccess\Application\Handlers\GetProfileQueryHandler;
use App\Modules\IdentityAccess\Application\Handlers\UpdateMyProfileCommandHandler;
use App\Modules\IdentityAccess\Application\Handlers\UpdateProfileCommandHandler;
use App\Modules\IdentityAccess\Application\Handlers\UploadMyAvatarCommandHandler;
use App\Modules\IdentityAccess\Application\Queries\GetMyProfileQuery;
use App\Modules\IdentityAccess\Application\Queries\GetProfileQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ProfileController
{
    public function me(GetMyProfileQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetMyProfileQuery)->toArray(),
        ]);
    }

    public function updateMe(Request $request, UpdateMyProfileCommandHandler $handler): JsonResponse
    {
        $result = $handler->handle(new UpdateMyProfileCommand($this->profilePatch($request)));

        return response()->json([
            'status' => 'profile_updated',
            'data' => $result->toArray(),
        ]);
    }

    public function uploadMyAvatar(Request $request, UploadMyAvatarCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        /** @var \Illuminate\Http\UploadedFile $avatar */
        $avatar = $validated['avatar'];
        $result = $handler->handle(new UploadMyAvatarCommand($avatar));

        return response()->json([
            'status' => 'avatar_uploaded',
            'data' => $result->toArray(),
        ]);
    }

    public function show(string $userId, GetProfileQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetProfileQuery($userId))->toArray(),
        ]);
    }

    public function update(string $userId, Request $request, UpdateProfileCommandHandler $handler): JsonResponse
    {
        $result = $handler->handle(new UpdateProfileCommand($userId, $this->profilePatch($request)));

        return response()->json([
            'status' => 'profile_updated',
            'data' => $result->toArray(),
        ]);
    }

    private function profilePatch(Request $request): ProfilePatchData
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'filled', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:16'],
            'timezone' => ['sometimes', 'nullable', 'timezone'],
        ]);
        $this->assertNonEmptyPatch($validated);

        return new ProfilePatchData(
            nameProvided: array_key_exists('name', $validated),
            name: $this->nullableString($validated, 'name'),
            phoneProvided: array_key_exists('phone', $validated),
            phone: $this->nullableString($validated, 'phone'),
            jobTitleProvided: array_key_exists('job_title', $validated),
            jobTitle: $this->nullableString($validated, 'job_title'),
            localeProvided: array_key_exists('locale', $validated),
            locale: $this->nullableString($validated, 'locale'),
            timezoneProvided: array_key_exists('timezone', $validated),
            timezone: $this->nullableString($validated, 'timezone'),
        );
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function assertNonEmptyPatch(array $validated): void
    {
        if ($validated === []) {
            throw ValidationException::withMessages([
                'payload' => ['At least one updatable field is required.'],
            ]);
        }
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function nullableString(array $validated, string $key): ?string
    {
        /** @var mixed $value */
        $value = $validated[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
