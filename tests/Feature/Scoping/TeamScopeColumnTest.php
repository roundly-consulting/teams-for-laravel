<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\UlidTeam;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\UuidTeam;

/**
 * @return array<string, mixed>
 */
function teamScopeColumn(string $column = 'team_id', bool $nullable = false): array
{
    $table = new Blueprint(DB::connection()->getSchemaBuilder()->getConnection(), 'scoped_things');
    $table->teamScope($column, $nullable);

    return collect($table->getColumns())->firstWhere('name', $column)->getAttributes();
}

it('types the column like the team model key, not like teams.key_type', function (?string $model, string $setting, string $type): void {
    config()->set('teams.key_type', $setting === 'bigint' ? 'ulid' : 'bigint');   // the morph-column setting is irrelevant here
    config()->set('teams.primary_key_type', $setting);

    if ($model !== null) {
        config()->set('teams.models.team', $model);
    }

    expect(teamScopeColumn()['type'])->toBe($type);
})->with([
    'packaged team, bigint' => [null, 'bigint', 'bigInteger'],
    'packaged team, uuid' => [null, 'uuid', 'uuid'],
    'packaged team, ulid' => [null, 'ulid', 'char'],
    // A subclass keying itself (the pre-setting way) wins over the setting, so its scoped
    // columns keep matching the ids it writes; the doctor flags the disagreement.
    'HasUlids team, setting left at bigint' => [UlidTeam::class, 'bigint', 'char'],
    'HasUuids team, setting left at bigint' => [UuidTeam::class, 'bigint', 'uuid'],
]);

it('indexes the column, constrains it onto the team table and can be nullable', function (): void {
    $table = new Blueprint(DB::connection()->getSchemaBuilder()->getConnection(), 'scoped_things');
    $foreign = $table->teamScope('company_id', nullable: true);

    $column = collect($table->getColumns())->firstWhere('name', 'company_id');

    expect($column->get('nullable'))->toBeTrue()
        ->and($column->get('index'))->toBeTrue()
        ->and($foreign->get('on'))->toBe('teams')
        ->and($foreign->get('references'))->toBe('id');
});
