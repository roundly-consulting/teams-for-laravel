# Changelog

All notable changes to `teams-for-laravel` will be documented in this file.

## Unreleased

### Added
- `Teams` facade and fluent `TeamBuilder` as a single, discoverable entry point.
- Action layer (`src/Actions`) and typed DTOs (`src/DataTransferObjects`) for every operation.
- Config-switchable role storage: in-memory (`array`) or database (`database`) driver behind a
  shared `RoleProvider` contract, plus a `Permission` value object.
- Team ownership (`owner` morph, `isOwnedBy`, `transferOwnershipTo`) with ownership transfer
  that demotes the previous owner to the configured admin role.
- Email-targeted invites with `invited_by`, `pending`/`forEmail` scopes, `revoke()`, and
  `code`-based route-model binding.
- Laravel Gate abilities and `@teamPermission` / `@teamRole` Blade directives (on by default,
  opt out with `TEAMS_REGISTER_GATE=false`).
- Query scopes (`Team::public`, `Team::withMember`) and richer `HasTeams` readers
  (`hasTeam`, `isMemberOf`, `teamsWithRole`, `ownedTeams`).
- Artisan commands `teams:roles` and `teams:invites:prune`.
- New events: `TeamMemberRoleChanged`, `InviteRevoked`, `TeamOwnershipTransferred`.
- Typed exceptions under `src/Exceptions` with translatable messages.

### Changed
- **Accepting an expired invite now throws `InviteExpiredException`** instead of silently
  adding the member. Use `$invite->isExpired()` to pre-check. This fixes a latent correctness
  bug where stale invite links could still be accepted.
- Adding an existing member is idempotent: it returns the existing membership and updates the
  role when it differs, instead of creating a duplicate.
