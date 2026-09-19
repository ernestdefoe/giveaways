<?php

namespace ErnestDefoe\Giveaways;

use Carbon\Carbon;
use Flarum\Locale\TranslatorInterface;
use Flarum\User\User;

/** Creates base entries and awards bonus entries, enforcing eligibility. */
class EntryService
{
    public function __construct(protected TranslatorInterface $translator)
    {
    }

    /** Returns a human (localized) reason the user can't enter, or null if eligible. */
    public function ineligibleReason(Giveaway $giveaway, User $user): ?string
    {
        if ($user->isGuest()) {
            return $this->translator->trans('ernestdefoe-giveaways.api.enter_login');
        }
        if (! $giveaway->isRunning()) {
            return $this->translator->trans('ernestdefoe-giveaways.api.enter_closed');
        }
        $s = $giveaway->settingsArray();
        if (($s['min_posts'] ?? 0) > 0 && (int) $user->comment_count < (int) $s['min_posts']) {
            return $this->translator->trans('ernestdefoe-giveaways.api.enter_min_posts', ['count' => (int) $s['min_posts']]);
        }
        if (($s['min_age_days'] ?? 0) > 0 && $user->joined_at && $user->joined_at->gt(Carbon::now()->subDays((int) $s['min_age_days']))) {
            return $this->translator->trans('ernestdefoe-giveaways.api.enter_too_new');
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
