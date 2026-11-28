<?php

namespace ErnestDefoe\Giveaways\Sponsors;

use Carbon\Carbon;
use ErnestDefoe\Giveaways\Advent\Calendar;
use ErnestDefoe\Giveaways\Advent\Clock;
use ErnestDefoe\Giveaways\Giveaway;
use Flarum\User\User;

/**
 * A sponsor's campaigns: standalone giveaways and advent calendars that name
 * it. (A calendar's own draws aren't campaigns: the calendar is.)
 */
class Campaigns
{
    public function __construct(private readonly Clock $clock)
    {
    }

    /** The campaign, if this viewer can see it, else null. */
    public function find(string $campaign, int $id, User $actor): Giveaway|Calendar|null
    {
        return match ($campaign) {
            OptIn::GIVEAWAY => Giveaway::query()->whereVisibleTo($actor)->whereNull('calendar_id')->find($id),
            OptIn::CALENDAR => Calendar::query()->whereVisibleTo($actor)->find($id),
            default => null,
        };
    }

    public static function kind(Giveaway|Calendar $c): string
    {
        return $c instanceof Calendar ? OptIn::CALENDAR : OptIn::GIVEAWAY;
    }

    public function startsAt(Giveaway|Calendar $c): ?Carbon
    {
        return $c instanceof Calendar
            ? Carbon::instance($this->clock->opensAt($c, 1)->toDateTime())
            : ($c->starts_at ?? $c->created_at);
    }

    public function endsAt(Giveaway|Calendar $c): Carbon
    {
        return $c instanceof Calendar ? Carbon::instance($this->clock->opensAt($c, $c->days + 1)->toDateTime()) : $c->ends_at;
    }

    /**
     * Everything the sponsor presents, newest first: as $viewer may see it,
     * or (for the report, $viewer null) everything published.
     *
     * @return list<Giveaway|Calendar>
     */
    public function forSponsor(Sponsor $sponsor, ?User $viewer): array
    {
        $giveaways = Giveaway::query()->where('sponsor_id', $sponsor->id)->whereNull('calendar_id')
            ->when($viewer, fn ($q) => $q->whereVisibleTo($viewer), fn ($q) => $q->where('status', '!=', 'draft'))
            ->orderByDesc('ends_at')->limit(50)->get()->all();
        $calendars = Calendar::query()->where('sponsor_id', $sponsor->id)
            ->when($viewer, fn ($q) => $q->whereVisibleTo($viewer), fn ($q) => $q->where('status', Calendar::PUBLISHED))
            ->orderByDesc('starts_on')->limit(20)->get()->all();

        $all = [...$calendars, ...$giveaways];
        usort($all, fn ($a, $b) => $this->endsAt($b) <=> $this->endsAt($a));

        return $all;
    }
}
