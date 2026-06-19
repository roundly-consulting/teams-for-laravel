<?php

declare(strict_types=1);

use RoundlyConsulting\Teams\DataTransferObjects\AcceptInviteData;
use RoundlyConsulting\Teams\DataTransferObjects\AddMemberData;
use RoundlyConsulting\Teams\DataTransferObjects\CreateInviteData;
use RoundlyConsulting\Teams\DataTransferObjects\CreateTeamData;
use RoundlyConsulting\Teams\Tests\User;

it('builds create team data with defaults', function () {
    $data = new CreateTeamData(name: 'Acme');

    expect($data)
        ->name->toBe('Acme')
        ->isPublic->toBeFalse()
        ->owner->toBeNull()
        ->meta->toBe([]);
});

it('builds create team data with an owner and meta', function () {
    $owner = new User;
    $data = new CreateTeamData(name: 'Acme', isPublic: true, owner: $owner, meta: ['plan' => 'pro']);

    expect($data)
        ->isPublic->toBeTrue()
        ->owner->toBe($owner)
        ->meta->toBe(['plan' => 'pro']);
});

it('builds add member data with defaults', function () {
    $member = new User;
    $data = new AddMemberData(member: $member, role: 'admin');

    expect($data)
        ->member->toBe($member)
        ->role->toBe('admin')
        ->meta->toBe([]);
});

it('builds create invite data with defaults', function () {
    $data = new CreateInviteData(role: 'admin');

    expect($data)
        ->role->toBe('admin')
        ->expiresAt->toBeNull()
        ->email->toBeNull()
        ->invitedBy->toBeNull()
        ->meta->toBe([]);
});

it('builds accept invite data with defaults', function () {
    $member = new User;
    $data = new AcceptInviteData(member: $member);

    expect($data)
        ->member->toBe($member)
        ->email->toBeNull();
});
