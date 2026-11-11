<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Teams\Exceptions\TeamContextException;
use RoundlyConsulting\Teams\Exceptions\TeamsException;
use RoundlyConsulting\Teams\Facades\Teams;
use RoundlyConsulting\Teams\Models\Team;
use RoundlyConsulting\Teams\Rules\UniqueInTeam;
use RoundlyConsulting\Teams\Scoping\TeamKey;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy\Document;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy\Project;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy\ScopedTicket;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\Healthy\Task;
use RoundlyConsulting\Teams\Tests\Fixtures\Scoping\VendorTicket;
use RoundlyConsulting\Teams\Tests\Support\TwoCompanies;

beforeEach(function (): void {
    $this->world = TwoCompanies::make();
});

describe('without a team context', function (): void {
    it('reads no rows at all — never every team\'s', function (): void {
        expect(Project::count())->toBe(0)
            ->and(Project::all())->toBeEmpty()
            ->and(Task::count())->toBe(0)
            ->and(Document::count())->toBe(0)
            ->and(Project::find($this->world->projectOf($this->world->a)->getKey()))->toBeNull()
            ->and(Project::withoutTeamScope()->count())->toBe(4);
    });

    it('throws instead when configured to', function (): void {
        config()->set('teams.scoping.without_context', 'throw');

        expect(fn () => Project::count())->toThrow(TeamContextException::class, 'No team context is set.');
    });

    it('rejects an unknown without_context value', function (): void {
        config()->set('teams.scoping.without_context', 'everything');

        expect(fn () => Project::count())->toThrow(InvalidConfigurationException::class);
    });

    it('refuses every write', function (): void {
        $foreign = $this->world->projectOf($this->world->b);

        expect(fn () => Project::create(['name' => 'Orphan']))->toThrow(TeamContextException::class)
            ->and(fn () => Project::create(['name' => 'Explicit', 'team_id' => $this->world->a->getKey()]))->toThrow(TeamContextException::class)
            ->and(fn () => $foreign->update(['name' => 'Hijacked']))->toThrow(TeamContextException::class)
            ->and(fn () => $foreign->delete())->toThrow(TeamContextException::class);

        expect(Project::withoutTeamScope()->where('name', 'Hijacked')->exists())->toBeFalse();
    });
});

describe('in team A\'s context', function (): void {
    beforeEach(function (): void {
        Teams::setCurrent($this->world->a);
    });

    it('reads only team A\'s rows', function (): void {
        $b = $this->world->projectOf($this->world->b);

        expect(Project::orderBy('name')->pluck('name')->all())->toBe(['Apollo', 'Gemini'])
            ->and(Project::pluck('team_id')->unique()->all())->toBe([$this->world->a->getKey()])
            ->and(Project::find($b->getKey()))->toBeNull()
            ->and(Project::whereKey($b->getKey())->exists())->toBeFalse()
            ->and(Document::count())->toBe(1)
            ->and(Task::count())->toBe(2);

        expect(fn () => Project::findOrFail($b->getKey()))->toThrow(ModelNotFoundException::class);
    });

    it('scopes eager-loaded and lazy relations through the scope', function (): void {
        $projects = Project::with('tasks')->get();

        expect($projects->flatMap->tasks->pluck('team_id')->unique()->all())->toBe([$this->world->a->getKey()]);

        // A relation from a model that is NOT scoped (the team of another company) is
        // still filtered: the related model carries the scope.
        expect($this->world->b->hasMany(Project::class, 'team_id')->get())->toBeEmpty()
            ->and(Project::whereHas('tasks')->count())->toBe(2)
            ->and(Task::with('project')->get()->pluck('project.team_id')->unique()->all())->toBe([$this->world->a->getKey()]);
    });

    it('never eager-loads a team B row hanging off a team A row', function (): void {
        // A corrupted cross-tenant link: a team A task pointing at a team B project.
        $b = $this->world->projectOf($this->world->b);

        Teams::withoutTeamScope(fn () => Task::create(['team_id' => $this->world->a->getKey(), 'project_id' => $b->getKey(), 'title' => 'Crossed']));

        $crossed = Task::with('project')->where('title', 'Crossed')->sole();

        expect($crossed->project)->toBeNull();
    });

    it('stamps new rows with team A', function (): void {
        $project = Project::create(['name' => 'Mercury']);
        $document = Document::create(['title' => 'NDA']);

        expect($project->team_id)->toBe($this->world->a->getKey())
            ->and($document->company_id)->toBe($this->world->a->getKey());
    });

    it('accepts team A explicitly and refuses team B', function (): void {
        expect(Project::create(['name' => 'Mine', 'team_id' => $this->world->a->getKey()])->exists)->toBeTrue();

        expect(fn () => Project::create(['name' => 'Planted', 'team_id' => $this->world->b->getKey()]))
            ->toThrow(TeamContextException::class, 'outside the current team context');

        expect(Project::withoutTeamScope()->where('name', 'Planted')->exists())->toBeFalse();
    });

    it('cannot update, move or delete a team B row, even one loaded through an escape hatch', function (): void {
        $foreign = Project::withoutTeamScope()->whereKey($this->world->projectOf($this->world->b)->getKey())->sole();

        expect(fn () => $foreign->update(['name' => 'Hijacked']))->toThrow(TeamContextException::class)
            ->and(fn () => $foreign->delete())->toThrow(TeamContextException::class)
            ->and(fn () => $foreign->forceDelete())->toThrow(TeamContextException::class);

        $mine = Project::where('name', 'Apollo')->sole();

        expect(fn () => $mine->update(['team_id' => $this->world->b->getKey()]))->toThrow(TeamContextException::class)
            ->and(fn () => $mine->switchTeamTo($this->world->b))->toThrow(TeamContextException::class);

        expect(Project::forTeam($this->world->b)->pluck('name')->sort()->values()->all())->toBe(['Apollo', 'Gemini'])
            ->and(Project::withoutTeamScope()->where('team_id', $this->world->b->getKey())->count())->toBe(2);
    });

    it('limits mass updates and deletes to team A', function (): void {
        Project::query()->update(['name' => 'Renamed']);
        Task::query()->delete();

        expect(Project::forTeam($this->world->b)->pluck('name')->sort()->values()->all())->toBe(['Apollo', 'Gemini'])
            ->and(Task::forTeam($this->world->b)->count())->toBe(2)
            ->and(Task::withoutTeamScope()->count())->toBe(2);
    });

    it('updates and soft-deletes its own rows normally', function (): void {
        $mine = Project::where('name', 'Apollo')->sole();

        $mine->update(['name' => 'Apollo II']);
        $mine->delete();
        $mine->restore();

        expect(Project::where('name', 'Apollo II')->exists())->toBeTrue();
    });

    it('opens explicit, greppable escape hatches', function (): void {
        expect(Project::withoutTeamScope()->count())->toBe(4)
            ->and(Project::forTeam($this->world->b)->count())->toBe(2)
            ->and(Project::forTeam([$this->world->a, $this->world->b])->count())->toBe(4)
            ->and(Project::forTeam($this->world->b->getKey())->count())->toBe(2)
            ->and(Project::forTeam([])->count())->toBe(0)
            ->and(Teams::withoutTeamScope(fn (): int => Project::count()))->toBe(4)
            ->and(Project::count())->toBe(2);

        expect(fn () => Project::forTeam(new Team))->toThrow(TeamContextException::class);
    });

    it('lets withoutTeamScope() write any team, and only inside the callback', function (): void {
        $foreign = Project::withoutTeamScope()->whereKey($this->world->projectOf($this->world->b)->getKey())->sole();

        Teams::withoutTeamScope(fn () => $foreign->update(['name' => 'Moved by admin']));

        expect(Project::forTeam($this->world->b)->where('name', 'Moved by admin')->exists())->toBeTrue()
            ->and(fn () => $foreign->update(['name' => 'Again']))->toThrow(TeamContextException::class);
    });

    it('restores the bypass after an exception', function (): void {
        try {
            Teams::withoutTeamScope(fn () => throw new RuntimeException('boom'));
        } catch (RuntimeException) {
        }

        expect(Project::count())->toBe(2);
    });
});

describe('a model subclassed from another package', function (): void {
    it('is scoped, while the parent package\'s own class stays untouched', function (): void {
        Teams::runAs($this->world->a, fn () => ScopedTicket::create(['subject' => 'Printer']));
        Teams::runAs($this->world->b, fn () => ScopedTicket::create(['subject' => 'Printer']));

        Teams::setCurrent($this->world->a);

        expect(ScopedTicket::count())->toBe(1)
            ->and(ScopedTicket::sole()->team_id)->toBe($this->world->a->getKey())
            // The limit the docs spell out: the parent class is not scoped.
            ->and(VendorTicket::count())->toBe(2);
    });
});

describe('multi-team mode', function (): void {
    it('reads every team in the set and writes only when the team is named', function (): void {
        $c = Team::factory()->create();
        Teams::for($c)->members()->add($this->world->alice, 'user');
        Teams::runAs($c, fn () => Project::create(['name' => 'Third']));
        Teams::for($this->world->b)->members()->add($this->world->alice, 'user');

        $this->actingAs($this->world->alice);
        Teams::setCurrentMany([$this->world->a, $this->world->b]);

        expect(Project::count())->toBe(4)
            ->and(Project::where('name', 'Third')->exists())->toBeFalse();

        expect(fn () => Project::create(['name' => 'Which team?']))->toThrow(TeamContextException::class, 'name the team explicitly')
            ->and(fn () => Project::create(['name' => 'Outside', 'team_id' => $c->getKey()]))->toThrow(TeamContextException::class);

        expect(Project::create(['name' => 'Named', 'team_id' => $this->world->b->getKey()])->team_id)->toBe($this->world->b->getKey());
    });
});

describe('the team-scoped unique rule', function (): void {
    it('checks uniqueness inside the current team only', function (): void {
        Teams::setCurrent($this->world->a);

        $rule = fn () => ['name' => [UniqueInTeam::for(Project::class, 'name')]];

        expect(Validator::make(['name' => 'Apollo'], $rule())->fails())->toBeTrue()
            ->and(Validator::make(['name' => 'Mercury'], $rule())->passes())->toBeTrue();

        Project::withoutTeamScope()->where('team_id', $this->world->a->getKey())->update(['name' => 'Renamed']);

        // Team B still has an "Apollo" — irrelevant to team A.
        expect(Validator::make(['name' => 'Apollo'], $rule())->passes())->toBeTrue();
    });

    it('ignores the row being updated, honours an explicit team and translates its message', function (): void {
        Teams::setCurrent($this->world->a);
        $apollo = Project::where('name', 'Apollo')->sole();

        expect(Validator::make(['name' => 'Apollo'], ['name' => [UniqueInTeam::for(Project::class, 'name')->ignore($apollo)]])->passes())->toBeTrue()
            ->and(Validator::make(['name' => 'Apollo'], ['name' => [UniqueInTeam::for(Project::class, 'name')->ignore($apollo->getKey())]])->passes())->toBeTrue();

        app()->setLocale('sk');
        $validator = Validator::make(['name' => 'Apollo'], ['name' => [UniqueInTeam::for(Project::class, 'name')->inTeam($this->world->b)]]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->first('name'))->toContain('v tomto tíme');
    });

    it('refuses to guess the team without a context or in multi-team mode', function (): void {
        $rule = ['name' => [UniqueInTeam::for(Project::class, 'name')]];

        expect(fn () => Validator::make(['name' => 'x'], $rule)->passes())->toThrow(TeamContextException::class);

        Teams::for($this->world->b)->members()->add($this->world->alice, 'user');
        Teams::setCurrentMany([$this->world->a, $this->world->b], $this->world->alice);

        expect(fn () => Validator::make(['name' => 'x'], $rule)->passes())->toThrow(TeamContextException::class, 'name the team explicitly')
            ->and(fn () => UniqueInTeam::for(Project::class, 'name')->inTeam(new Team))->toThrow(TeamContextException::class);
    });
});

describe('scoped keys', function (): void {
    it('prefixes caches and paths per team', function (): void {
        $a = $this->world->a;

        expect($a->scopedKey('files'))->toBe('teams/'.$a->getKey().'/files')
            ->and($a->scopedKey('/cache/stats/'))->toBe('teams/'.$a->getKey().'/cache/stats')
            ->and($a->scopedKey())->toBe('teams/'.$a->getKey())
            ->and($a->scopedKey('files'))->not->toBe($this->world->b->scopedKey('files'));

        Teams::setCurrent($a);

        expect(Teams::current()?->scopedKey('files'))->toBe('teams/'.$a->getKey().'/files');
    });

    it('refuses a suffix that climbs out of the team prefix', function (string $suffix): void {
        expect(fn () => $this->world->a->scopedKey($suffix))->toThrow(TeamsException::class);
    })->with(['../2/files', 'files/../../2', './x', 'a\\..\\b', "x\0y"]);

    it('refuses an unsaved team', function (): void {
        expect(fn () => (new Team)->scopedKey('files'))->toThrow(TeamContextException::class);
    });
});

describe('the teamScope() migration macro', function (): void {
    it('adds an indexed column constrained onto teams', function (): void {
        $schema = (new Project)->getConnection()->getSchemaBuilder();

        $foreign = collect($schema->getForeignKeys('projects'))->firstWhere('columns', ['team_id']);
        $index = collect($schema->getIndexes('documents'))->first(fn (array $index): bool => $index['columns'] === ['company_id']);

        expect($foreign)->not->toBeNull()
            ->and($foreign['foreign_table'])->toEndWith('teams')
            ->and($foreign['foreign_columns'])->toBe(['id'])
            ->and($index)->not->toBeNull()
            ->and($schema->getColumnType('projects', 'team_id'))->toBeIn(match (TeamKey::type()) {
                KeyType::BigInt => ['integer', 'bigint', 'int8'],
                KeyType::Uuid => ['uuid', 'char', 'varchar'],
                KeyType::Ulid => ['bpchar', 'char', 'varchar'],
            });
    });
});
