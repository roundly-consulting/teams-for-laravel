# Changelog

All notable changes to `teams-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- Teams that any Eloquent model can own and join (`HasTeams`, `BelongsToTeam`), with members,
  roles and permissions.
- A `Teams` facade with a fluent builder (`Teams::for($team)->addMember(...)`), backed by action
  classes and DTOs.
- Roles defined in code or stored in the database (`Roles::register()`), per-team role
  overrides, an optional cache, and a `Permissions` registry for picker UIs.
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
- `Teams::fake()` with assertions and Pest expectation matchers for tests.
