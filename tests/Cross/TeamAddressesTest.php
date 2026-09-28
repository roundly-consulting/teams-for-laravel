<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\AddressBook;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;

it('creates typed addresses for a team', function (): void {
    $team = Team::factory()->create();

    $team->createAddress(
        city: 'London',
        street: '1 King St',
        postalCode: 'EC1A 1AA',
        countryIsoCode: 'GB',
        type: AddressType::Billing,
    );

    $team->createAddress(
        city: 'Berlin',
        street: '2 Haupt St',
        postalCode: '10115',
        countryIsoCode: 'DE',
        type: AddressType::Default,
    );

    expect($team->addresses()->count())->toBe(2)
        ->and($team->getAddressOfType(AddressType::Billing)?->city)->toBe('London')
        ->and($team->getAddressOfType(AddressType::Default)?->city)->toBe('Berlin');
});

it('resolves the primary address of a type', function (): void {
    $team = Team::factory()->create();

    $team->createAddress(
        city: 'Paris',
        street: '3 Rue de Rivoli',
        postalCode: '75001',
        countryIsoCode: 'FR',
        isPrimary: true,
        type: AddressType::Billing,
    );

    expect($team->getPrimaryAddressOfType(AddressType::Billing)?->city)->toBe('Paris');
});

it('persists addresses against the team addressable morph', function (): void {
    $team = Team::factory()->create();

    $address = $team->createAddress(
        city: 'Madrid',
        street: '4 Gran Via',
        postalCode: '28013',
        countryIsoCode: 'ES',
        type: AddressType::Office,
    );

    expect((string) $address->addressable_type)->toBe($team->getMorphClass())
        ->and((string) $address->addressable_id)->toBe((string) $team->getKey());
});

it('delegates to the addresses package through the team handle', function (): void {
    $team = Team::factory()->create();

    $addresses = Teams::for($team)->addresses();

    $addresses->add(new AddressData(
        city: 'Rome',
        street: '5 Via Roma',
        postalCode: '00184',
        countryIso: 'IT',
        type: AddressType::Billing,
        isPrimary: true,
    ));

    expect($addresses)->toBeInstanceOf(AddressBook::class)
        ->and($addresses->primary(AddressType::Billing)?->city)->toBe('Rome')
        ->and($team->getPrimaryAddressOfType(AddressType::Billing)?->city)->toBe('Rome');
});
