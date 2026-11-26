<?php

namespace ErnestDefoe\Giveaways;

use Carbon\Carbon;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * A draft is seen by the member who made it and nobody else — not even a
 * manager — until it is published (by hand, or by posting the post it was
 * made in).
 *
 * An advent calendar's giveaways are kept to what its doors show: the
 * grand prize from the first door on, and a door's draw only to members
 * who have opened that door. Those who build calendars see them all.
 *
 * Registered with Extend\ModelVisibility, so core's whereVisibleTo() (and the
 * alerts whose subject is a giveaway) apply it everywhere.
 */
class ScopeGiveawayVisibility
{
    /** @param Builder<Giveaway> $query */
    public function __invoke(User $actor, Builder $query): void
    {
        $query->where(function (Builder $q) use ($actor) {
            $q->where('status', '!=', 'draft');
            if (! $actor->isGuest()) {
                $q->orWhere(fn (Builder $q) => $q->where('status', 'draft')->where('user_id', $actor->id));
            }
        });

        if (! $actor->hasPermission('giveaways.manage')) {
            $query->where(fn (Builder $q) => $q
                ->whereNull('calendar_id')
                // The grand prize from the first door on, like the calendar shows it.
                ->orWhere(fn (Builder $q) => $q->where('calendar_role', 'grand')->where('starts_at', '<=', Carbon::now()))
                // A door's draw only to members who opened that door: its title and
                // prize are what's behind the door.
                ->orWhere(fn (Builder $q) => $q->where('calendar_role', 'door')->whereExists(
                    fn ($opened) => $opened->selectRaw('1')
                        ->from('giveaway_calendar_doors')
                        ->join('giveaway_calendar_opens', fn ($join) => $join
                            ->on('giveaway_calendar_opens.calendar_id', '=', 'giveaway_calendar_doors.calendar_id')
                            ->on('giveaway_calendar_opens.day', '=', 'giveaway_calendar_doors.day'))
                        ->whereColumn('giveaway_calendar_doors.giveaway_id', 'giveaways.id')
                        ->where('giveaway_calendar_opens.user_id', $actor->id)
                )));
        }
    }
}
