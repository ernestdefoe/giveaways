<?php

namespace ErnestDefoe\Giveaways;

use Carbon\Carbon;
use Flarum\User\User;

/** Creates base entries and awards bonus entries, enforcing eligibility. */
class EntryService
{
    /** Returns a human reason the user can't enter, or null if eligible. */
    public function ineligibleReason(Giveaway $giveaway, User $user): ?string
    {
        if ($user->isGuest()) {
            return 'You must be logged in to enter.';
        }
        if (! $giveaway->isRunning()) {
            return 'This giveaway is not open for entries.';
        }
        $s = $giveaway->settingsArray();
        if (($s['min_posts'] ?? 0) > 0 && (int) $user->comment_count < (int) $s['min_posts']) {
            return 'You need at least ' . (int) $s['min_posts'] . ' posts to enter.';
        }
        if (($s['min_age_days'] ?? 0) > 0 && $user->joined_at && $user->joined_at->gt(Carbon::now()->subDays((int) $s['min_age_days']))) {
            return 'Your account is too new to enter this giveaway.';
        }
        return null;
    }

    /** Idempotent base entry. Caller should check ineligibleReason() first. */
    public function enter(Giveaway $giveaway, User $user): GiveawayEntry
    {
        $entry = GiveawayEntry::query()
            ->where('giveaway_id', $giveaway->id)->where('user_id', $user->id)->first();
        if ($entry) {
            return $entry;
        }

        $entry = new GiveawayEntry();
        $entry->giveaway_id = $giveaway->id;
        $entry->user_id = $user->id;
        $entry->entries = 1;
        $entry->sources = json_encode(['base' => 1]);
        $entry->created_at = Carbon::now();
        $entry->updated_at = Carbon::now();
        $entry->save();

        return $entry;
    }

    /**
     * Award $n bonus entries under a named source, once per source, only to users
     * who have already entered a running giveaway. No-op otherwise.
     */
    public function addBonus(Giveaway $giveaway, User $user, string $source, int $n): void
    {
        if ($n <= 0 || ! $giveaway->isRunning()) {
            return;
        }
        $entry = GiveawayEntry::query()
            ->where('giveaway_id', $giveaway->id)->where('user_id', $user->id)->first();
        if (! $entry) {
            return; // must have entered first
        }
        $sources = $entry->sourcesArray();
        if (isset($sources[$source])) {
            return; // already awarded this source
        }
        $sources[$source] = $n;
        $entry->sources = json_encode($sources);
        $entry->entries = array_sum($sources);
        $entry->updated_at = Carbon::now();
        $entry->save();
    }
}
