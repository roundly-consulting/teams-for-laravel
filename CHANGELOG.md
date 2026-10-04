# Changelog

All notable changes to `teams-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

## 1.0.2 - 2026-10-04

### Fixed

- The `TeamsException` thrown when an invite-only team receives a join request, and the one thrown when a team's `MaxSeats` cap is reached, are now translated into the current locale (English and Slovak). The seat-limit message names the cap with proper plural forms ("This team has reached its seat limit (3 seats).").
- The `InvalidConfigurationException` thrown for an invalid interval setting (`teams.invites.expires_after`, `teams.members.prune_after`, `teams.join_requests.prune_after`) is now translated into the current locale (English and Slovak); the English wording is unchanged.

## 1.0.1 - 2026-10-04

### Changed

- Maintenance: `composer.json` `homepage` and `support.docs` now point to the documentation site.

### Fixed

- Slovak (`sk`) translations now ship alongside English for every language file.

## 1.0.0 - 2026-10-03

Initial public release.

### Added

- Teams that any Eloquent model can own and join (`HasTeams`, `BelongsToTeam`), with members,
  roles and permissions.
- One public API in three layers: the `Teams` facade, the injectable `TeamsManager` behind it,
  and the action classes it runs. `Teams::create()`; `Teams::for($team)` returns a scoped
  handle with `members()->add/remove/changeRole/all/has/find`,
  `invites()->create/resend/revoke/pending`,
  `joinRequests()->requireApprovalFrom/rule/quorum/open/approve/deny/pending`,
  `roles()->define/all/find`, `transferOwnershipTo()`, `settings()`, `contacts()`,
  `addresses()` and `connections()`; flat `Teams::invites()->accept/find/prune`,
  `Teams::members()->expiring/notifyExpiring/prune`, `Teams::joinRequests()->expire()`,
  `Teams::roles()` and `Teams::permissions()`.
- Roles defined in code or stored in the database (`Teams::roles()->register()`), per-team role
  overrides, an optional cache, and a permission registry for picker UIs
  (`Teams::permissions()->register/group/find/all/fromRoles`).
- Ownership transfer and a first-class owner ability, including the `@teamOwner` Blade directive.
- Time-boxed memberships with expiry reports, renewal events and pruning.
- Expiring, email-targeted and multi-use invites with resend and revoke, accepted by code.
- Join requests (the inverse of invites) with approve / deny and optional expiry.
- Laravel Gate integration plus `@teamPermission` and `@teamRole` Blade directives, and team
  policies (`AbstractTeamPolicy`, `HasTeamPolicies`, `teams:policy`).
- Artisan commands to list roles and permissions, prune invites, members and join requests, and
  report expiring memberships.
- Events for members, roles, invites, ownership, join requests and membership expiry, plus
  publishable controller, policy and notification stubs.
- Team settings, contacts, addresses, multi-admin join-request sign-off and team affiliations
  via the companion `options`, `contacts`, `addresses`, `approvals` and `connections` packages.
- `Teams::fake()` — a recording, still-performing `TeamsFake` (a `TeamsManager` subtype, so
  injected managers get it too) that sees calls made through the facade, the handles, the model
  methods, the commands and the approvals listener, with `assertTeamCreated`,
  `assertMemberAdded/Removed`, `assertRoleChanged`, `assertInviteCreated/Resent/Revoked/Accepted`,
  `assertJoinRequested`, `assertJoinRequestApproved/Denied`, `assertRoleDefined`,
  `assertOwnershipTransferred`, the pruning asserts and a negative for each; plus Pest
  expectation matchers.
- `MemberNotFoundException` and `JoinRequestNotFoundException`.

### Changed

- The facade root is `RoundlyConsulting\Teams\TeamsManager` (was `RoundlyConsulting\Teams\Teams`
  behind the `'teams'` container key); resolve it by class. `TeamBuilder` is replaced by
  `Handles\TeamHandle` and its sub-accessors.
- `Teams::createTeam()` is `Teams::create()`; `Teams::acceptInvite()` and
  `Teams::acceptInviteByCode()` merge into `Teams::invites()->accept(Invite|string, $user, email:)`;
  `Teams::requestToJoin($team, …)` is `Teams::for($team)->joinRequests()->open(…)`;
  `Teams::role($key)` is `Teams::roles()->find($key)`, and `Teams::roles()` / `permissions()`
  return the role provider and permission registry instead of arrays.
- The static `Roles` and `Permissions` classes are removed — use `Teams::roles()` and
  `Teams::permissions()`.
- Builder methods moved under sub-accessors: `addMember/removeMember/changeRole` →
  `members()->add/remove/changeRole` (returning the `Member` / `bool`), `invite/resendInvite/
  revokeInvite` → `invites()->create/resend/revoke`, `defineRole` → `roles()->define`,
  `approveJoinRequest/denyJoinRequest($r, $by)` → `joinRequests()->approve/deny($r, by:)`,
  `requireApprovalFrom()->…->requestToJoinFor()` → `joinRequests()->requireApprovalFrom()->…->open()`
  (now immutable), `transferOwnershipTo()` returns the `Team`. `addContactEmail/Phone/Url`,
  `addAddress`, `connectTo` and `disconnectFrom` are replaced by `contacts()`, `addresses()` and
  `connections()`, which return the companion packages' own handles.
- `Team::invite()` takes the role first, like the handle:
  `invite(string $role, ?CarbonInterface $expiresAt = null, ?string $email = null, ?Model $invitedBy = null, array $meta = [], ?int $maxUses = 1)`.
- `Team::addMember/removeMember/invite/defineRole/roles`, `Invite::acceptBy/resend/revoke` and
  `Member::removeFromTeam()` delegate to the manager; `removeFromTeam()` returns `bool`.
- `ChangeMemberRoleAction::execute(Team, Model, string)` throws `MemberNotFoundException` for a
  non-member instead of the builder silently doing nothing.
- `DispatchExpiringMembershipsAction::execute()` defaults the window to
  `teams.members.expiring_within`; `RequestToJoinData` carries the staged approvers, rule and
  quorum, and `RequestToJoinAction` opens the approval request itself.
- The commands and the approvals listener run through the manager.

### Fixed

- A team-scoped handle no longer acts on another team's rows: `Teams::for($teamA)` refuses to
  approve or deny a join request, or resend or revoke an invite, that belongs to another team.
- Re-opening an already-pending join request with staged approvers no longer opens a second
  approval request.
