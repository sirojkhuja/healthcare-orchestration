# Definition of Done

A task is done only when all of the following are true:

- implementation is complete
- relevant task status is `Done`
- progress percentage is updated
- code or documentation changes are committed
- changes are pushed to the remote
- formatting passes
- linting passes
- static analysis passes
- tests pass
- build passes
- verification passes
- hardening checks pass
- release dry run passes for release workflow changes
- OpenAPI fragments and generated bundle are updated or marked as unaffected
- module and workflow docs are updated
- ADR is updated when architectural decisions changed
- observability and security requirements are satisfied
- approved readiness, cutover, and rollback documents exist for release-readiness changes

If any item is missing, the task remains incomplete.
