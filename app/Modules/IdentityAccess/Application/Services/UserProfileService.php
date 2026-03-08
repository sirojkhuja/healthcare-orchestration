<?php

namespace App\Modules\IdentityAccess\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\IdentityAccess\Application\Contracts\ManagedUserRepository;
use App\Modules\IdentityAccess\Application\Contracts\ProfileAvatarStore;
use App\Modules\IdentityAccess\Application\Contracts\ProfileRepository;
use App\Modules\IdentityAccess\Application\Data\ProfilePatchData;
use App\Modules\IdentityAccess\Application\Data\UserProfileData;
use App\Shared\Application\Contracts\TenantContext;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UserProfileService
{
    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
        private readonly TenantContext $tenantContext,
        private readonly ManagedUserRepository $managedUserRepository,
        private readonly ProfileRepository $profileRepository,
        private readonly ProfileAvatarStore $profileAvatarStore,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function myProfile(): UserProfileData
    {
        return $this->profileOrFail($this->authenticatedRequestContext->current()->user->id);
    }

    public function updateMyProfile(ProfilePatchData $patch): UserProfileData
    {
        return $this->updateProfile($this->authenticatedRequestContext->current()->user->id, $patch, 'self');
    }

    public function uploadMyAvatar(UploadedFile $avatar): UserProfileData
    {
        $profile = $this->myProfile();
        $storedAvatar = $this->profileAvatarStore->storeForUser($profile->userId, $avatar);
        $updated = $this->profileRepository->updateAvatar($profile->userId, $storedAvatar);
        $this->profileAvatarStore->delete($profile->avatar);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'profiles.avatar_uploaded',
            objectType: 'user',
            objectId: $updated->userId,
            before: $profile->toArray(),
            after: $updated->toArray(),
            metadata: ['source' => 'self'],
        ));

        return $updated;
    }

    public function profileForTenant(string $userId): UserProfileData
    {
        $this->tenantUserOrFail($userId);

        return $this->profileOrFail($userId);
    }

    public function updateProfileForTenant(string $userId, ProfilePatchData $patch): UserProfileData
    {
        $this->tenantUserOrFail($userId);

        return $this->updateProfile($userId, $patch, 'admin');
    }

    private function updateProfile(string $userId, ProfilePatchData $patch, string $source): UserProfileData
    {
        $profile = $this->profileOrFail($userId);

        if ($patch->isEmpty() || $this->profileMatchesPatch($profile, $patch)) {
            return $profile;
        }

        $updated = $this->profileRepository->update($userId, $patch);

        if ($profile->toArray() !== $updated->toArray()) {
            $this->auditTrailWriter->record(new AuditRecordInput(
                action: 'profiles.updated',
                objectType: 'user',
                objectId: $updated->userId,
                before: $profile->toArray(),
                after: $updated->toArray(),
                metadata: ['source' => $source],
            ));
        }

        return $updated;
    }

    private function profileOrFail(string $userId): UserProfileData
    {
        $profile = $this->profileRepository->findById($userId);

        if ($profile === null) {
            throw new NotFoundHttpException('The requested user profile could not be found.');
        }

        return $profile;
    }

    private function tenantUserOrFail(string $userId): void
    {
        $tenantId = $this->tenantContext->requireTenantId();

        if ($this->managedUserRepository->findInTenant($userId, $tenantId) === null) {
            throw new NotFoundHttpException('The requested user does not belong to the active tenant.');
        }
    }

    private function profileMatchesPatch(UserProfileData $profile, ProfilePatchData $patch): bool
    {
        $updates = $patch->toDatabaseUpdates();

        foreach ($updates as $field => $value) {
            $currentValue = match ($field) {
                'name' => $profile->name,
                'phone' => $profile->phone,
                'job_title' => $profile->jobTitle,
                'locale' => $profile->locale,
                'timezone' => $profile->timezone,
                default => null,
            };

            if ($currentValue !== $value) {
                return false;
            }
        }

        return true;
    }
}
