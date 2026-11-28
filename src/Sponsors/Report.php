<?php

namespace ErnestDefoe\Giveaways\Sponsors;

use ErnestDefoe\Giveaways\Advent\Opening;
use ErnestDefoe\Giveaways\GiveawayEntry;
use Illuminate\Database\Eloquent\Builder;

/**
 * A sponsor's campaign report: counts only. No names, no emails, no ids.
 * A count from 1 to 4 is shown as "fewer than 5", so a small campaign can't
 * single anyone out.
 *
 * Grouped counts for every campaign at once: the query count doesn't grow
 * with campaigns or entrants.
 */
class Report
{
    public const THRESHOLD = 5;

    /** Campaigns per new-members query (three bindings each). */
    private const WINDOWS = 100;

    public function __construct(private readonly Campaigns $campaigns)
    {
    }

    /** @return list<array<string, mixed>> */
    public function rows(Sponsor $sponsor): array
    {
        $list = $this->campaigns->forSponsor($sponsor, null);
        $ids = [OptIn::GIVEAWAY => [], OptIn::CALENDAR => []];
        $windows = [OptIn::GIVEAWAY => [], OptIn::CALENDAR => []];
        foreach ($list as $c) {
            $kind = Campaigns::kind($c);
            $ids[$kind][] = $c->id;
            if ($starts = $this->campaigns->startsAt($c)) {
                $windows[$kind][$c->id] = [$starts, $this->campaigns->endsAt($c)];
            }
        }

        $stats = $this->perCampaign(Stat::query()->where('sponsor_id', $sponsor->id), 'SUM(views) as views, SUM(clicks) as clicks');
        $optIns = $this->perCampaign(OptIn::query()->where('sponsor_id', $sponsor->id), 'COUNT(*) as n');
        $entrants = [
            OptIn::GIVEAWAY => $this->counts(GiveawayEntry::query(), 'giveaway_id', $ids[OptIn::GIVEAWAY]),
            OptIn::CALENDAR => $this->counts(Opening::query(), 'calendar_id', $ids[OptIn::CALENDAR]),
        ];
        $newMembers = [
            OptIn::GIVEAWAY => $this->newMembers(fn () => GiveawayEntry::query(), 'giveaway_entries', 'giveaway_id', $windows[OptIn::GIVEAWAY]),
            OptIn::CALENDAR => $this->newMembers(fn () => Opening::query(), 'giveaway_calendar_opens', 'calendar_id', $windows[OptIn::CALENDAR]),
        ];

        return array_map(function ($c) use ($stats, $optIns, $entrants, $newMembers) {
            $kind = Campaigns::kind($c);
            $key = $kind.':'.$c->id;

            return [
                'title' => $c->title,
                'kind' => $kind,
                'starts' => $this->campaigns->startsAt($c),
                'ends' => $this->campaigns->endsAt($c),
                'views' => (int) ($stats[$key]->views ?? 0),
                'clicks' => (int) ($stats[$key]->clicks ?? 0),
                'entrants' => $entrants[$kind][$c->id] ?? 0,
                // Entrants whose account was made while the campaign ran.
                'newMembers' => $newMembers[$kind][$c->id] ?? 0,
                'optIns' => (int) ($optIns[$key]->n ?? 0),
            ];
        }, $list);
    }

    /** A count as the report prints it: 1 to 4 become null ("fewer than 5"). */
    public static function shown(int $n): ?int
    {
        return $n > 0 && $n < self::THRESHOLD ? null : $n;
    }

    /**
     * One grouped query over the sponsor's rows, keyed "kind:id". The raw
     * part is a fixed aggregate, no input.
     *
     * @return array<string, object>
     */
    private function perCampaign(Builder $query, string $aggregates): array
    {
        $out = [];
        foreach ($query->selectRaw("campaign, campaign_id, $aggregates")->groupBy('campaign', 'campaign_id')->toBase()->get() as $row) {
            $out[$row->campaign.':'.$row->campaign_id] = $row;
        }

        return $out;
    }

    /**
     * Distinct members per campaign, in one grouped query.
     *
     * @param list<int> $ids
     * @return array<int, int>
     */
    private function counts(Builder $query, string $column, array $ids): array
    {
        if (! $ids) {
            return [];
        }
        $grammar = $query->getQuery()->getGrammar();

        return $query->whereIn($column, $ids)
            ->selectRaw($grammar->wrap($column).' as cid, COUNT(DISTINCT '.$grammar->wrap('user_id').') as n')
            ->groupBy($column)->toBase()->pluck('n', 'cid')->map(fn ($n) => (int) $n)->all();
    }

    /**
     * Entrants who joined the forum while each campaign ran: one grouped
     * query per 100 campaigns, joining users (never a list of user ids).
     *
     * @param callable(): Builder $query
     * @param array<int, array{0: \Carbon\Carbon, 1: \Carbon\Carbon}> $windows
     * @return array<int, int>
     */
    private function newMembers(callable $query, string $table, string $column, array $windows): array
    {
        $out = [];
        foreach (array_chunk($windows, self::WINDOWS, true) as $chunk) {
            $q = $query();
            $grammar = $q->getQuery()->getGrammar();
            $q->join('users', 'users.id', '=', "$table.user_id")
                ->where(function ($w) use ($chunk, $table, $column) {
                    foreach ($chunk as $id => [$starts, $ends]) {
                        $w->orWhere(fn ($one) => $one->where("$table.$column", $id)->whereBetween('users.joined_at', [$starts, $ends]));
                    }
                })
                // Wrapped by the grammar, so the table prefix applies.
                ->selectRaw($grammar->wrap("$table.$column").' as cid, COUNT(DISTINCT '.$grammar->wrap('users.id').') as n')
                ->groupBy("$table.$column");
            $out += $q->toBase()->pluck('n', 'cid')->map(fn ($n) => (int) $n)->all();
        }

        return $out;
    }
}
