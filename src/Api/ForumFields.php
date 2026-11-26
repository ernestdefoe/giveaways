<?php

namespace ErnestDefoe\Giveaways\Api;

use Flarum\Api\Context;
use Flarum\Api\Schema;

/**
 * The forum resource's `canCreateGiveaways`, which shows the composer's "Add
 * giveaway" button. A permission the actor's groups were already loaded for:
 * no query.
 */
class ForumFields
{
    /** @return list<Schema\Boolean> */
    public function __invoke(): array
    {
        return [
            Schema\Boolean::make('canCreateGiveaways')
                ->get(fn ($forum, Context $context) => $context->getActor()->hasPermission('giveaways.create')),
        ];
    }
}
