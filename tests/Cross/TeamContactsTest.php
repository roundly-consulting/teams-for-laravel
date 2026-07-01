<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;

it('adds email, phone and url contacts to a team', function (): void {
    $team = Team::factory()->create();

    $team->addEmail('support@acme.io', 'support');
    $team->addPhone('+441234567890', 'ops');
    $team->addUrl('https://acme.io', 'site');

    expect($team->contacts()->count())->toBe(3)
        ->and($team->contactsOfType(ContactType::Email)->first()?->value)->toBe('support@acme.io');
});

it('tracks the primary contact per type', function (): void {
    $team = Team::factory()->create();

    $team->addEmail('first@acme.io', 'first', primary: true);
    $team->addEmail('second@acme.io', 'second', primary: true);

    expect($team->primaryEmail()?->value)->toBe('second@acme.io')
        ->and($team->contactsOfType(ContactType::Email))->toHaveCount(2);
});

it('persists contacts against the team owner morph', function (): void {
    $team = Team::factory()->create();

    $contact = $team->addEmail('billing@acme.io', 'billing', primary: true);

    expect($contact->owner_type)->toBe($team->getMorphClass())
        ->and((string) $contact->owner_id)->toBe((string) $team->getKey());
});

it('adds contacts fluently through the team builder', function (): void {
    $team = Team::factory()->create();

    Teams::for($team)->addContactEmail('help@acme.io', 'help', primary: true);
    Teams::for($team)->addContactPhone('+15551234567', 'hotline');
    Teams::for($team)->addContactUrl('https://status.acme.io', 'status');

    expect($team->primaryEmail()?->value)->toBe('help@acme.io')
        ->and($team->contactsOfType(ContactType::Phone))->toHaveCount(1)
        ->and($team->contactsOfType(ContactType::Url))->toHaveCount(1);
});
