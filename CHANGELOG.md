# Changelog

All notable changes to `teams-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

## 2.4.0 - 2026-11-13

### Added

- `RoundlyConsulting\Teams\Contracts\WithoutTeamContext`: a marker for queued jobs that take their team as an argument (seat sync, metering). Its payload carries no team context, so nothing is restored and `teams.context.recheck_queued_jobs` never fails it — such jobs are typically dispatched exactly when a member leaves or a team is deleted. Honoured on jobs, queued listeners, queued notifications and queued mailables, encrypted or not. The job runs with no current team, so it must scope its queries by the team it receives (`Teams::runAs($team, …)`, `forTeam()`). On the `sync` queue the dispatcher gets its team context back after the job.
- `Teams::withoutContext(Closure $callback)` (`RunWithoutTeamContextAction`): runs the callback with the team context suspended — jobs dispatched inside carry none — and restores it afterwards, also on an exception; nests safely. A returned `PendingDispatch` is dispatched inside and `null` returned, like `runAs()`. Not recorded by `Teams::fake()`.

## 2.3.0 - 2026-11-11

### Added

- `teams.primary_key_type` (`TEAMS_PRIMARY_KEY_TYPE`: `bigint`, `uuid` or `ulid`; default `bigint`) keys the teams themselves. It types `teams.id` and every column pointing at a team — `team_id` on `team_invites`, `team_members`, `team_join_requests` and `team_role_overrides`, `teams.parent_id`, and the `$table->teamScope()` column — and the packaged `Team` model mints its own time-ordered UUID (v7) or lowercase ULID. Set it before migrating; a typo throws `InvalidConfigurationException`. Teams can now be ULID-keyed without a model subclass or hand-edited migrations.
- `php artisan teams:doctor` checks the team key: it fails when the configured team model keys differently from `teams.primary_key_type` (for example a `HasUlids` subclass with the setting left at `bigint`) or a migrated team column cannot hold the key (an integer `team_id` for ULID teams). Programmatic: `Teams::teamKeys()` returns a `TeamKeyReport` (`InspectTeamKeysAction`).

### Changed

- A `teams.models.team` subclass that uses `HasUlids`/`HasUuids` itself keeps working, and `teamScope()` keeps following its key. Set `teams.primary_key_type` to match it; the doctor reports the mismatch until you do. Bigint hosts change nothing: the migrations emit exactly the same schema.
- Maintenance: CI also runs the suite with ULID-keyed teams on PostgreSQL.

### Fixed

- Per-team role overrides (`Teams::for($team)->roles()->define()`) work for UUID/ULID teams. `DefineTeamRoleData::$teamId` is `int|string`; the team key was cast to `int`, which turned a ULID into `0`.
- `@property` types of `Team::$id` / `$parent_id` and the `team_id` of `Member`, `Invite`, `JoinRequest` and `TeamRole` are `int|string`.

## 2.2.0 - 2026-11-09

### Added

- Optional bridge to `roundly-consulting/permissions-for-laravel` ^2.1 (`teams.permissions.bridge`, off by default; the package is a `suggest`, never a `require`). With it on, roles and permissions granted to a member inside a team (`Permissions::for($user)->in($team)`) count in `Team::memberHasPermission()` / `memberHasRole()`, `HasTeams::hasTeamPermission()` / `hasTeamRole()`, the `teams.<permission>` Gate abilities and the `@teamPermission` / `@teamRole` directives — alongside the membership role, and only while the membership is active (expiry and start dates are read at check time). With roles inheriting down, a grant held in a team above counts for its members too.
- With the bridge on, scope-less Gate checks (`can:` middleware, `@can('x')`, `$user->can('x')`) resolve in the current team for its active members, through `Permissions::resolveScopeUsing()`. Multi-team mode and non-members get no team scope. A host resolver registered later replaces it.
- With the bridge on, a member's grants in a team are forgotten when the member is removed, when an expired membership is pruned and when a lapsed membership is re-added (it starts afresh, as before); a force-deleted team forgets every grant made in it (`Permissions::forgetScope()`). A soft-deleted team keeps them.
- `Teams::for($team)->members()->grant($user)` — the member's grants in the team: `->assignRole()`, `->givePermissionTo()`, `->syncRoles()`, `->syncPermissions()`, `->removeRole()`, `->revokePermissionTo()`, `->forgetAllAuthorization()`, the windows `->from()`, `->until()`, `->between()`, and the reads `->hasRole()`, `->hasPermissionTo()`, `->assignments()`. An addition needs a current or booked membership (`MemberNotFoundException` otherwise) and ends when the membership expires unless `until()` says otherwise. Actions `GrantMemberAuthorizationAction` / `RevokeMemberAuthorizationAction`, operations `TeamOperation::GrantMemberAuthorization` / `RevokeMemberAuthorization`, and `TeamsFake::assertMemberGranted()`, `assertNothingGranted()`, `assertMemberGrantRevoked()`, `assertNoGrantRevoked()`.
- `php artisan about` shows the bridge (`Permissions bridge ON|OFF`).
- Turning it on: `composer require roundly-consulting/permissions-for-laravel`, publish and run its migrations, set `PERMISSIONS_ASSIGNMENTS=true`, then `TEAMS_PERMISSIONS_BRIDGE=true`. With the switch on but the package missing or its assignments off, the application fails at boot with an `InvalidConfigurationException` rather than ignoring the switch.

### Changed

- `composer.json` declares `conflict: roundly-consulting/permissions-for-laravel <2.1`: an app that has both packages needs permissions 2.1 or later (any 2.x app updates with `composer update roundly-consulting/permissions-for-laravel`). Apps without permissions are unaffected.

## 2.1.0 - 2026-11-09

### Added

- `Teams::switchTo($team, ?$user)`: the checked way to set the current team. It sets the team only when the user (by default the authenticated one) holds an active membership of it, or of a team above it when roles inherit down. Otherwise it throws `TeamContextException` and leaves the context as it was. Use it for any team a user picked; `Teams::setCurrent()` stays the unchecked call for system code.
- Queued jobs can check the membership again when they start (`teams.context.recheck_queued_jobs`, off by default). A context set through a membership check (the `teams.context` middleware, `Teams::switchTo()`, `Teams::setCurrentMany()`) remembers the member it was checked for, and jobs dispatched from it carry that member. With the option on, a job whose member was removed, expired or deleted after dispatch (or whose team was deleted) fails with `TeamContextException` before it runs. It is not retried, and its context is cleared. Contexts set with `Teams::setCurrent()` are not re-checked.
- `Team::hasActiveMember()`, `Teams::for($team)->members()->hasActive()` and `HasTeams::isActiveMemberOf()`: membership checks that ignore expired memberships.
- Membership start dates (`teams.members.start_dates`, off by default). `Teams::for($team)->members()->add($user, $role, startsAt: …)` (also `Team::addMember()` and `AddMemberData::$startsAt`) books a membership that begins later. Until then the member gets no access, role or permission: `active()`, `hasActiveMember()`, `isAccessibleBy()`, the `teams.context` middleware, `Teams::switchTo()`, the queued-job re-check, role and permission checks and the Gate all honour the start date. A booked membership holds its seat against `MaxSeats`. New `Member::hasStarted()`, `Member::isActive()`, the `unexpired()` and `upcoming()` scopes and `MemberFactory::startingAt()`. A start date given while the option is off, or one not before the expiry, throws `TeamsException`.
- Upgrading: to use start dates, re-run `php artisan vendor:publish --tag=teams-migrations` (adds the nullable `team_members.starts_at` column), run `php artisan migrate`, then switch `teams.members.start_dates` on. With the option off, nothing reads or writes the column.

### Changed

- The `teams.context` middleware sets the team through `Teams::switchTo()`. It runs the same membership check and returns the same deny status as before; the context now also remembers the user.
- Documentation: `Teams::setCurrent()` is marked as unchecked, for system code only.

### Deprecated

- `Team::hasMember()` and `Teams::for($team)->members()->has()`: they check only that a membership row exists, so an expired membership still counts. Use `hasActiveMember()` / `hasActive()`.

### Planned for the next major

- `teams.context.recheck_queued_jobs` on by default.
- `hasMember()`, `members()->has()`, `belongsToTeam()` and `isMemberOf()` ignore expired memberships, like `hasActiveMember()`.
- `teams.members.start_dates` on by default. The `starts_at` migration is then required.

## 2.0.0 - 2026-11-07

### Changed

- **Breaking:** requires Laravel 13; Laravel 12 is no longer supported. Upgrade the application to Laravel 13 before updating.
- Requires the 2.x releases of the Roundly packages it builds on (their Laravel 13 releases).
- Maintenance: tested on PHP 8.4, 8.5 and 8.6; the test suite runs on Pest 5.

## 1.1.0 - 2026-11-07

### Added

- Per-team data isolation for multi-tenant apps. Everything is opt-in; existing `BelongsToTeam`, `HasTeams`, roles and queries behave exactly as before.
- Current team context: `Teams::current()`, `Teams::currentMany()`, `Teams::setCurrent()`, `Teams::setCurrentMany()` (only teams the user is an active member of), `Teams::runAs()` (restores the previous context, also on an exception) and `Teams::for($team)->run()`. The context rides Laravel's hidden Context, so queued jobs restore the team(s) they were dispatched with; console commands and scheduled tasks start without one. `TeamContextSet` fires on every change.
- `teams.context` route middleware: binds the team by its route key (`{team:slug}` works), lets through only active members, answers 404 (or 403 via `teams.context.deny_status`) to everyone else, and runs before `SubstituteBindings` so team-scoped route models bind inside the context.
- `ScopedToTeam` model trait: a global scope that fails closed (no context → no rows, or `TeamContextException` with `teams.scoping.without_context = throw`), stamps new rows with the current team and refuses any write for another team. Escape hatches: `Model::withoutTeamScope()`, `Model::forTeam($team)`, `Teams::withoutTeamScope(fn () => …)`. Per-model column via `const TEAM_COLUMN`.
- `$table->teamScope()` migration macro: the team column typed like the team model's key, indexed and constrained onto `teams`.
- `UniqueInTeam` validation rule, `Team::scopedKey()` for per-team cache keys and storage paths, and `Team::isAccessibleBy()`.
- `php artisan teams:doctor` and `Teams::scopedModels()`: fail when a scoped table lacks its team column or an index on it.
- `InteractsWithTeams` testing trait with `actingInTeam($user, $team)`.
- Nested teams (off by default, `teams.nesting.*`): an additive, nullable `teams.parent_id` migration, `Teams::for($team)->moveUnder()` / `moveToRoot()`, `parent()`, `children()`, `root()`, `ancestors()`, `descendants()`, `depth()`, cycle and `max_depth` checks, the `TeamMoved` event and `TeamsFake::assertTeamMoved()`. With `inherit_roles_down` a role held on a team applies on every team below it, in permission and role checks, the Gate, Blade directives, team policies and the context middleware.
- Upgrading: re-run `php artisan vendor:publish --tag=teams-migrations` to add the nesting migration, then `php artisan migrate`.

### Changed

- Documentation: the README pitch mentions per-team data isolation and nested teams.
- Maintenance: dev dependency `roundly-consulting/testing-for-laravel` `^1.6`; the real-engine CI legs run on tmpfs.

## 1.0.4 - 2026-10-17

### Fixed

- Factories build the configured model when a host swaps it.

## 1.0.3 - 2026-10-11

### Changed

- Maintenance: requires the latest roundly packages — package-toolkit `^1.3.0`, addresses `^1.0.1`, approvals `^1.2.3`, connections `^1.1.0`, contacts `^1.1.1`, enums `^1.1.0`, options `^1.1.0`; dev: testing `^1.2.1`.
- Documentation: the README hero image uses an absolute URL, so it renders on Packagist and other sites.

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
