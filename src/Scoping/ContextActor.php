<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Scoping;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The member a team context was checked for, as a morph type + key — plain scalars, so it
 * rides Laravel's hidden Context into a queued job and back without serialising a model.
 *
 * @internal
 */
final readonly class ContextActor
{
    public function __construct(
        public string $type,
        public int|string $id,
    ) {}

    public static function of(Model $member): self
    {
        $key = $member->getKey();

        return new self($member->getMorphClass(), is_int($key) ? $key : (string) (is_scalar($key) ? $key : ''));
    }

    /**
     * Read back what {@see self::toStored()} wrote; anything else is no actor.
     */
    public static function fromStored(mixed $stored): ?self
    {
        if (! is_array($stored)) {
            return null;
        }

        $type = $stored['type'] ?? null;
        $id = $stored['id'] ?? null;

        if (! is_string($type) || $type === '' || ! (is_int($id) || (is_string($id) && $id !== ''))) {
            return null;
        }

        return new self($type, $id);
    }

    /**
     * The hidden-Context payload: scalars only.
     *
     * @return array<string, int|string>
     */
    public function toStored(): array
    {
        return ['type' => $this->type, 'id' => $this->id];
    }

    /**
     * The member model as it is now, or null when its class is gone or the row is (soft-)
     * deleted — a member that no longer exists can act in no team.
     */
    public function resolve(): ?Model
    {
        $class = Relation::getMorphedModel($this->type) ?? $this->type;

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        /** @var Model|null $member */
        $member = $class::query()->whereKey($this->id)->first();

        return $member;
    }
}
