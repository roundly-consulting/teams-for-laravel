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

it('types the column like the team model key, not like teams.key_type', function (?string $model, string $type): void {
    config()->set('teams.key_type', 'ulid');   // the morph-column setting is irrelevant here

    if ($model !== null) {
        config()->set('teams.models.team', $model);
    }

    expect(teamScopeColumn()['type'])->toBe($type);
})->with([
    'packaged team' => [null, 'bigInteger'],
    'HasUlids team' => [UlidTeam::class, 'char'],
    'HasUuids team' => [UuidTeam::class, 'uuid'],
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
