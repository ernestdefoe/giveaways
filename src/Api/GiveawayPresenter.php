<?php

namespace ErnestDefoe\Giveaways\Api;

use ErnestDefoe\Giveaways\Giveaway;
use ErnestDefoe\Giveaways\GiveawayEntry;
use ErnestDefoe\Giveaways\GiveawayWinner;
use ErnestDefoe\Giveaways\RichText;
use Flarum\Discussion\Discussion;
use Flarum\User\User;
use Illuminate\Support\Collection;

/**
 * What one viewer sees of a giveaway: GiveawayResource's fields read their
 * values from here, so who may see what is decided in one place (the skill
 * answer, claim instructions and per-winner lines, the editable source, a
 * private discussion's id).
 *
 * For a list, build it with {@see forList()} so the per-row aggregates (entrant
 * count, total entries) and the actor's own entry/win are batch-loaded once
 * instead of issuing a fresh query per giveaway (the old N+1).
 */
class GiveawayPresenter
{
    /**
     * @param Collection<array-key, GiveawayEntry>|null  $myEntries  actor's entry, keyed by giveaway_id
     * @param Collection<array-key, GiveawayWinner>|null $myWins     actor's winner row, keyed by giveaway_id
     * @param Collection<array-key, GiveawayEntry>|null  $aggregates entrant_count + total_entries, keyed by giveaway_id
     */
    public function __construct(
        protected User $actor,
        protected RichText $rich,
        protected ?Collection $myEntries = null,
        protected ?Collection $myWins = null,
        protected ?Collection $aggregates = null
    ) {
    }

    /** A presenter for a single giveaway (per-row lookups are fine for one row). */
    public static function forActor(User $actor, RichText $rich): self
    {
        return new self($actor, $rich);
    }

    /** A presenter primed with batch-loaded data for a whole list — no N+1. */
    public static function forList(User $actor, Collection $giveaways, RichText $rich): self
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

        return new self($actor, $rich, $myEntries, $myWins, $aggregates);
    }

    public function present(Giveaway $g, bool $full = false): array
    {
        $s = $g->settingsArray();
        $canManage = $g->canBeManagedBy($this->actor);

        $data = $this->giveaway($g, $s, $canManage) + $this->mine($g, $s, $canManage);

        return $full ? $data + $this->detail($g, $canManage) : $data;
    }

    /**
     * The giveaway itself, the same for everyone but the parts only its
     * managers get.
     *
     * @param array<string, mixed> $s settings
     * @return array<string, mixed>
     */
    private function giveaway(Giveaway $g, array $s, bool $canManage): array
    {
        [$entrantCount, $totalEntries] = $this->counts($g);

        return [
            'id' => (int) $g->id,
            'title' => $g->title,
            'slug' => $g->slug,
            'prize' => $g->prize,
            // The question is public so entrants can answer it; the expected
            // answer only ever goes back to someone who can manage the giveaway
            // (it populates the edit form) — never to an entrant.
            'skillQuestion' => $g->requiresSkillAnswer() ? (string) $s['skill_question'] : null,
            'skillAnswer' => $canManage ? (string) ($s['skill_answer'] ?? '') : null,
            // Wrong answers allowed before the winner forfeits (0 = unlimited).
            'skillAttempts' => $g->skillAttemptLimit(),
            'coverUrl' => $g->cover_url,
            'winnerCount' => (int) $g->winner_count,
            'status' => $g->status,
            'startsAt' => $g->starts_at?->toIso8601String(),
            'endsAt' => $g->ends_at?->toIso8601String(),
            'drawnAt' => $g->drawn_at?->toIso8601String(),
            'running' => $g->isRunning(),
            'entrantCount' => $entrantCount,
            'totalEntries' => $totalEntries,
            'postBonus' => (int) ($s['post_bonus'] ?? 0),
            'minPosts' => (int) ($s['min_posts'] ?? 0),
            'minAgeDays' => (int) ($s['min_age_days'] ?? 0),
            // The whole per-winner list fills the edit form; it never goes to a winner.
            'claimLines' => $canManage ? (string) ($s['claim_lines'] ?? '') : null,
            'createdBy' => $g->user ? self::user($g->user) : null,
            // The advent calendar it belongs to: entered by opening its doors.
            'calendar' => $g->calendar_id && $g->calendar ? [
                'slug' => $g->calendar->slug,
                'title' => $g->calendar->title,
                'role' => $g->calendar_role,
            ] : null,
            'category' => $g->category ? [
                'id' => (int) $g->category->id,
                'name' => $g->category->name,
                'slug' => $g->category->slug,
                'color' => $g->category->color,
                'icon' => $g->category->icon,
            ] : null,
        ];
    }

    /**
     * The viewer's own side: what they may do, their entries, and their win.
     *
     * @param array<string, mixed> $s settings
     * @return array<string, mixed>
     */
    private function mine(Giveaway $g, array $s, bool $canManage): array
    {
        $myEntry = $this->myEntry($g);
        $myWin = $this->myWin($g);
        $myClaimed = $myWin && $myWin->claimed_at && ! $myWin->isForfeited();

        return [
            'myEntries' => $myEntry ? (int) $myEntry->entries : 0,
            'mySources' => $myEntry ? $myEntry->sourcesArray() : null,
            'canManage' => $canManage,
            'canDraw' => $canManage && $g->canBeDrawnBy($this->actor),
            'canDelete' => $canManage && $g->canBeDeletedBy($this->actor),
            'canViewParticipants' => $g->participantsVisibleTo($this->actor),
            'isHost' => ! $this->actor->isGuest() && $g->user_id && (int) $g->user_id === (int) $this->actor->id,
            'iWon' => (bool) $myWin,
            'myClaimedAt' => $myWin?->claimed_at?->toIso8601String(),
            'myForfeited' => $myWin ? $myWin->isForfeited() : false,
            // Null when there is no limit; otherwise what this winner has left.
            'myAttemptsLeft' => ($myWin && $g->skillAttemptLimit() > 0)
                ? max(0, $g->skillAttemptLimit() - (int) $myWin->skill_attempts)
                : null,
            // Instructions go to a winner only once they have claimed (after any
            // skill question), and to those who manage the giveaway.
            'claimInstructions' => ($myClaimed || $canManage) ? (string) ($s['claim_instructions'] ?? '') : null,
            // A winner's own line of the per-winner list, and only theirs.
            'myClaimLine' => $myClaimed ? $g->claimLineFor((int) $myWin->position) : null,
        ];
    }

    /**
     * One giveaway's page: its formatted text, winners, discussion and proof.
     *
     * @return array<string, mixed>
     */
    private function detail(Giveaway $g, bool $canManage): array
    {
        // Rendered by the forum's formatter, as a post is. The editable
        // source only goes to whoever can edit it.
        $data = [
            'descriptionHtml' => $this->rich->html($g->description, $g),
            'rulesHtml' => $this->rich->html($g->rules, $g),
        ];
        if ($canManage) {
            $data['description'] = $this->rich->source($g->description, $g);
            $data['rules'] = $this->rich->source($g->rules, $g);
        }

        return $data + [
            'winners' => $g->winners()->orderBy('position')->orderBy('id')->with('user')->get()
                ->map(fn ($w) => [
                    'position' => (int) $w->position,
                    'user' => $w->user ? self::user($w->user) : null,
                    'claimedAt' => $w->claimed_at?->toIso8601String(),
                    // Kept in the list on purpose: a forfeited winner is part of
                    // the record of how the prize moved.
                    'forfeited' => $w->isForfeited(),
                ])->all(),
            // The discussion it was posted in, unless this viewer can't see it
            // (a private discussion's id is not theirs to learn).
            'discussionId' => $g->discussion_id
                && Discussion::query()->whereVisibleTo($this->actor)->whereKey($g->discussion_id)->exists()
                ? (int) $g->discussion_id : null,
            'drawSeed' => $g->draw_seed,
            'entrantHash' => $g->entrant_hash,
        ];
    }

    /**
     * Entrants and total entries: batch-loaded on a list (a giveaway missing
     * from the aggregates has none), counted for a single giveaway.
     *
     * @return array{0: int, 1: int}
     */
    private function counts(Giveaway $g): array
    {
        if ($this->aggregates !== null) {
            $agg = $this->aggregates->get($g->id);

            // Aggregate columns from the grouped query, not model attributes.
            return $agg ? [(int) $agg->getAttribute('entrant_count'), (int) $agg->getAttribute('total_entries')] : [0, 0];
        }

        return [(int) $g->entries()->count(), (int) $g->entries()->sum('entries')];
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
            'id' => (int) $u->id,
            'username' => $u->username,
            'displayName' => $u->display_name,
            'avatarUrl' => $u->avatar_url,
        ];
    }
}
