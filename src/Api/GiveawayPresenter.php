<?php

namespace ErnestDefoe\Giveaways\Api;

use ErnestDefoe\Giveaways\Giveaway;
use ErnestDefoe\Giveaways\GiveawayEntry;
use ErnestDefoe\Giveaways\GiveawayWinner;
use Flarum\User\User;
use Illuminate\Support\Collection;

/**
 * Builds the plain giveaway JSON the forum frontend consumes via the custom
 * /api/giveaways controllers.
 *
 * This is deliberately NOT a Flarum/JSON:API serializer: those giveaways are
 * served as plain JSON (not JSON:API documents), so there's no AbstractSerializer
 * to extend here — the JSON:API surface lives in Api\Resource\GiveawayResource.
 * Calling it a "presenter" keeps that distinction clear.
 *
 * For a list, build it with {@see forList()} so the per-row aggregates (entrant
 * count, total entries) and the actor's own entry/win are batch-loaded once
 * instead of issuing a fresh query per giveaway (the old N+1).
 */
class GiveawayPresenter
{
    /**
     * @param Collection<int, GiveawayEntry>|null  $myEntries  actor's entry, keyed by giveaway_id
     * @param Collection<int, GiveawayWinner>|null $myWins     actor's winner row, keyed by giveaway_id
     * @param Collection<int, object>|null         $aggregates entrant_count + total_entries, keyed by giveaway_id
     */
    public function __construct(
        protected User $actor,
        protected ?Collection $myEntries = null,
        protected ?Collection $myWins = null,
        protected ?Collection $aggregates = null
    ) {}

    /** A presenter for a single giveaway (per-row lookups are fine for one row). */
    public static function forActor(User $actor): self
    {
        return new self($actor);
    }

    /** A presenter primed with batch-loaded data for a whole list — no N+1. */
    public static function forList(User $actor, Collection $giveaways): self
    {
        $ids = $giveaways->pluck('id')->all();

        $aggregates = GiveawayEntry::query()
            ->whereIn('giveaway_id', $ids)
            ->selectRaw('giveaway_id, COUNT(*) as entrant_count, SUM(entries) as total_entries')
            ->groupBy('giveaway_id')
            ->get()->keyBy('giveaway_id');

        $myEntries = $actor->isGuest() ? new Collection() : GiveawayEntry::query()
            ->whereIn('giveaway_id', $ids)->where('user_id', $actor->id)
            ->get()->keyBy('giveaway_id');

        $myWins = $actor->isGuest() ? new Collection() : GiveawayWinner::query()
            ->whereIn('giveaway_id', $ids)->where('user_id', $actor->id)
            ->get()->keyBy('giveaway_id');

        return new self($actor, $myEntries, $myWins, $aggregates);
    }

    public function present(Giveaway $g, bool $full = false): array
    {
        $agg = $this->aggregates?->get($g->id);
        $entrantCount = $agg ? (int) $agg->entrant_count : (int) $g->entries()->count();
        $totalEntries = $agg ? (int) $agg->total_entries : (int) $g->entries()->sum('entries');

        $myEntry = $this->myEntry($g);
        $myWin = $this->myWin($g);

        $s = $g->settingsArray();
        $canManage = $g->canBeManagedBy($this->actor);

        $data = [
            'id'           => (int) $g->id,
            'title'        => $g->title,
            'slug'         => $g->slug,
            'prize'        => $g->prize,
            'description'  => $g->description,
            'coverUrl'     => $g->cover_url,
            'winnerCount'  => (int) $g->winner_count,
            'status'       => $g->status,
            'startsAt'     => optional($g->starts_at)->toIso8601String(),
            'endsAt'       => optional($g->ends_at)->toIso8601String(),
            'drawnAt'      => optional($g->drawn_at)->toIso8601String(),
            'running'      => $g->isRunning(),
            'entrantCount' => $entrantCount,
            'totalEntries' => $totalEntries,
            'myEntries'    => $myEntry ? (int) $myEntry->entries : 0,
            'mySources'    => $myEntry ? $myEntry->sourcesArray() : null,
            'postBonus'    => (int) ($s['post_bonus'] ?? 0),
            'minPosts'     => (int) ($s['min_posts'] ?? 0),
            'minAgeDays'   => (int) ($s['min_age_days'] ?? 0),
            'canManage'    => $canManage,
            'iWon'         => (bool) $myWin,
            'myClaimedAt'  => $myWin ? optional($myWin->claimed_at)->toIso8601String() : null,
            // Instructions are only meaningful to winners and managers.
            'claimInstructions' => ($myWin || $canManage) ? (string) ($s['claim_instructions'] ?? '') : null,
            'createdBy'    => $g->user ? self::user($g->user) : null,
            'category'     => $g->category ? [
                'id'    => (int) $g->category->id,
                'name'  => $g->category->name,
                'slug'  => $g->category->slug,
                'color' => $g->category->color,
                'icon'  => $g->category->icon,
            ] : null,
        ];

        if ($full) {
            $data['winners'] = $g->winners()->orderBy('position')->with('user')->get()
                ->map(fn ($w) => [
                    'position'  => (int) $w->position,
                    'user'      => $w->user ? self::user($w->user) : null,
                    'claimedAt' => optional($w->claimed_at)->toIso8601String(),
                ])->all();
            $data['drawSeed'] = $g->draw_seed;
            $data['entrantHash'] = $g->entrant_hash;
        }

        return $data;
    }

    protected function myEntry(Giveaway $g): ?GiveawayEntry
    {
        if ($this->actor->isGuest()) {
            return null;
        }
        if ($this->myEntries !== null) {
            return $this->myEntries->get($g->id);
        }
        return $g->entries()->where('user_id', $this->actor->id)->first();
    }

    protected function myWin(Giveaway $g): ?GiveawayWinner
    {
        if ($this->actor->isGuest()) {
            return null;
        }
        if ($this->myWins !== null) {
            return $this->myWins->get($g->id);
        }
        return $g->winners()->where('user_id', $this->actor->id)->first();
    }

    private static function user(User $u): array
    {
        return [
            'id'          => (int) $u->id,
            'username'    => $u->username,
            'displayName' => $u->display_name,
            'avatarUrl'   => $u->avatar_url,
        ];
    }
}
