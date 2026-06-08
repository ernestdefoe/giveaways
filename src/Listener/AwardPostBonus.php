<?php

namespace ErnestDefoe\Giveaways\Listener;

use ErnestDefoe\Giveaways\EntryService;
use ErnestDefoe\Giveaways\Giveaway;
use ErnestDefoe\Giveaways\GiveawayEntry;
use Flarum\Post\Event\Posted;
use Psr\Log\LoggerInterface;

/**
 * When an entrant makes a post, grant the configured one-time "post" bonus on
 * every running giveaway they've already entered. addBonus() is idempotent per
 * source.
 *
 * Scoped to giveaways the user has actually entered (addBonus is a no-op
 * otherwise), so the work per post is bounded by that user's entries — not a
 * full scan of every active giveaway on the forum.
 */
class AwardPostBonus
{
    public function __construct(
        protected EntryService $entries,
        protected LoggerInterface $log
    ) {}

    public function handle(Posted $event): void
    {
        $user = $event->actor;
        if (! $user || $user->isGuest()) {
            return;
        }

        try {
            $giveaways = Giveaway::query()
                ->where('status', 'active')
                ->whereIn('id', GiveawayEntry::query()
                    ->where('user_id', $user->id)
                    ->select('giveaway_id'))
                ->get();

            foreach ($giveaways as $giveaway) {
                if (! $giveaway->isRunning()) {
                    continue;
                }
                $bonus = (int) ($giveaway->settingsArray()['post_bonus'] ?? 0);
                if ($bonus > 0) {
                    $this->entries->addBonus($giveaway, $user, 'post', $bonus);
                }
            }
        } catch (\Throwable $e) {
            // Never let bonus accounting break the act of posting.
            $this->log->warning('[giveaways] post-bonus award failed for user '
                . $user->id . ': ' . $e->getMessage());
        }
    }
}
