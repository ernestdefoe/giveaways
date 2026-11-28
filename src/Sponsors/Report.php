<?php

namespace ErnestDefoe\Giveaways\Sponsors;

use ErnestDefoe\Giveaways\Advent\Calendar;
use ErnestDefoe\Giveaways\Advent\Opening;
use ErnestDefoe\Giveaways\GiveawayEntry;
use Flarum\User\User;

/**
 * A sponsor's campaign report: counts only. No names, no emails, no ids.
 * A count from 1 to 4 is shown as "fewer than 5", so a small campaign can't
 * single anyone out.
 */
class Report
{
    public const THRESHOLD = 5;

    public function __construct(private readonly Campaigns $campaigns)
    {
    }

    /** @return list<array<string, mixed>> */
    public function rows(Sponsor $sponsor): array
    {
        $rows = [];
        foreach ($this->campaigns->forSponsor($sponsor, null) as $c) {
            $kind = Campaigns::kind($c);
            $starts = $this->campaigns->startsAt($c);
            $ends = $this->campaigns->endsAt($c);
            $entrants = $c instanceof Calendar
                ? Opening::query()->where('calendar_id', $c->id)->distinct()->pluck('user_id')
                : GiveawayEntry::query()->where('giveaway_id', $c->id)->pluck('user_id');
            $stats = Stat::query()->where('sponsor_id', $sponsor->id)->where('campaign', $kind)->where('campaign_id', $c->id);

            $rows[] = [
                'title' => $c->title,
                'kind' => $kind,
                'starts' => $starts,
                'ends' => $ends,
                'views' => (int) (clone $stats)->sum('views'),
                'clicks' => (int) (clone $stats)->sum('clicks'),
                'entrants' => $entrants->count(),
                // Entrants whose account was made while the campaign ran.
                'newMembers' => $starts ? User::query()->whereIn('id', $entrants)->whereBetween('joined_at', [$starts, $ends])->count() : 0,
                'optIns' => OptIn::query()->where('sponsor_id', $sponsor->id)->where('campaign', $kind)->where('campaign_id', $c->id)->count(),
            ];
        }

        return $rows;
    }

    /** A count as the report prints it: 1 to 4 become null ("fewer than 5"). */
    public static function shown(int $n): ?int
    {
        return $n > 0 && $n < self::THRESHOLD ? null : $n;
    }
}
