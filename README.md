<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/teams-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=teams-for-laravel">
    <img src="art/hero.png" alt="Teams for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/teams-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/teams-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/teams-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/teams-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/teams-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/teams-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=teams-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Teams for Laravel

Teams, roles, permissions and invitations for any Eloquent model. Members carry a role, roles
carry permissions checked through Laravel's Gate, and teams issue expiring, email-targeted
invites and handle join requests.

## Installation

Requires PHP 8.4 and Laravel 12 or 13.

```bash
composer require roundly-consulting/teams-for-laravel
php artisan vendor:publish --tag="options-migrations"
php artisan vendor:publish --tag="contacts-migrations"
php artisan vendor:publish --tag="addresses-migrations"
php artisan vendor:publish --tag="connections-migrations"
php artisan vendor:publish --tag="approvals-migrations"
php artisan vendor:publish --tag="teams-migrations"
php artisan migrate
```

Publish every tag: none of these packages auto-load their migrations, and the very first
`Teams::create()` needs the `options` table. If your users have UUID/ULID keys, set
`TEAMS_KEY_TYPE` **before** migrating.

## Usage

Register your roles in a service provider's `boot` method and add `HasTeams` to your user:

```php
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Traits\HasTeams;

Teams::roles()->register('owner', 'Owner', ['*']);
Teams::roles()->register('editor', 'Editor', ['posts.edit', 'posts.publish']);
Teams::roles()->register('member', 'Member', ['posts.view']);

class User extends Authenticatable
{
    use HasTeams;
}
```

Then create a team, seat members and invite people:

```php
use RoundlyConsulting\Teams\DataTransferObjects\CreateTeamData;
use RoundlyConsulting\Teams\Facades\Teams;

$team = Teams::create(new CreateTeamData(name: 'Acme', owner: $owner));   // owner joins as 'owner'

Teams::for($team)->members()->add($alice, 'editor');

$invite = Teams::for($team)->invites()->create(role: 'member', email: 'jane@acme.test');
Teams::invites()->accept($invite->code, $jane, email: $jane->email);

$alice->can('teams.posts.publish', $team);    // true
$jane->hasTeamRole($team, 'member');          // true
$owner->can('teams.owner', $team);            // true
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/teams-for-laravel](https://roundly-consulting.com/open-source/docs/teams-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=teams-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=teams-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=teams-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
