<?php

namespace ErnestDefoe\Giveaways\Advent;

use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/** Published calendars for everyone; drafts for those who build calendars. */
class ScopeCalendarVisibility
{
    /** @param Builder<Calendar> $query */
    public function __invoke(User $actor, Builder $query): void
    {
        if (! $actor->hasPermission('giveaways.manage')) {
            $query->where('giveaway_calendars.status', Calendar::PUBLISHED);
        }
    }
}
