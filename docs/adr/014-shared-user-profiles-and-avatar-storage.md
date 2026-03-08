# ADR 014: Shared User Profiles and Avatar Storage

## Status

Accepted

## Date

2026-03-09

## Context

The canonical route catalog defines self-service and administrative profile endpoints plus avatar upload, but it does not define which profile fields exist, whether profiles are tenant-owned or shared with the global identity account, how profile permissions apply, or how avatar storage should integrate with the shared storage abstractions introduced in `T010`.

## Decision

Use shared user profiles backed by the `users` table and tenant-scoped administrative access.

- Profile data is shared with the global identity account and stored on the `users` table.
- The initial profile field set is `name`, `phone`, `job_title`, `locale`, `timezone`, and avatar metadata.
- `GET /profiles/me`, `PATCH /profiles/me`, and `POST /profiles/me/avatar` are self-service endpoints. They require bearer authentication but do not require tenant context or `profiles.*` permissions.
- `GET /profiles/{userId}` and `PATCH /profiles/{userId}` are tenant-admin endpoints. They require tenant context plus `profiles.view` or `profiles.manage`, and the target user must belong to the active tenant membership scope.
- Profile updates change shared identity/profile data and become visible across every tenant membership held by that user.
- Avatar uploads use the shared attachment storage abstraction and store the current avatar metadata on the user record.
- Avatar responses expose profile-safe metadata only: filename, mime type, size, and upload time. They do not expose a public file URL or storage-internal disk/path details.
- Uploading a new avatar replaces the active avatar pointer and performs best-effort deletion of the previously referenced avatar file.

## Alternatives Considered

- make profiles tenant-owned instead of shared with the identity account
- require tenant context for all self-service profile endpoints
- store avatars on the public disk instead of through the shared attachment abstraction
- expose raw storage paths or direct public URLs in the profile response

## Consequences

- Administrative profile reads and updates stay tenant-safe, while self-service profile operations remain available even when no tenant context is active.
- Shared profile fields such as `name` can now be updated through both user-management and profile-management endpoints, so documentation must keep their responsibilities explicit.
- Avatar upload is ready for future media-delivery endpoints without forcing the profile API to expose storage internals today.
- Replacing an avatar may leave an orphaned file only if storage cleanup fails after the new avatar is already persisted.

## Migration Plan

- add shared profile and avatar metadata columns to `users`
- expose self-service and tenant-admin profile endpoints
- integrate avatar upload with the shared attachment storage abstraction
- update identity-access documentation, OpenAPI, and tests for self-service and tenant-admin profile behavior
