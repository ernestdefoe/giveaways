<?php

namespace ErnestDefoe\Giveaways\Api\Resource;

use ErnestDefoe\Giveaways\Giveaway;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as BaseContext;

/**
 * Minimal JSON:API resource for giveaways (type `giveaways`).
 *
 * The forum frontend reads giveaways through the custom plain-JSON controllers;
 * this resource exists so that giveaways are a registered JSON:API type. That
 * registration is REQUIRED for the `giveawayWon` notification to serialize its
 * subject — without it, Flarum's notifications endpoint 500s when resolving the
 * subject relationship's resource type.
 */
class GiveawayResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'giveaways';
    }

    public function model(): string
    {
        return Giveaway::class;
    }

    public function scope(Builder $query, BaseContext $context): void
    {
        // Giveaways are public; no per-row gating needed.
    }

    public function endpoints(): array
    {
        // No HTTP endpoints: the forum uses the custom plain-JSON controllers at
        // /api/giveaways. Registering endpoints here would collide with those
        // routes. We only need the resource TYPE registered so notification
        // subjects (and any ?include=subject) can serialize.
        return [];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('title'),
            Schema\Str::make('slug'),
            Schema\Str::make('prize'),
            Schema\Str::make('status'),
            Schema\DateTime::make('endsAt')->property('ends_at')->nullable(),
            Schema\DateTime::make('drawnAt')->property('drawn_at')->nullable(),
        ];
    }
}
