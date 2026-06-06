<?php

namespace ErnestDefoe\Giveaways\Listener;

use ErnestDefoe\Giveaways\EntryService;
use ErnestDefoe\Giveaways\Giveaway;
use Flarum\Post\Event\Posted;

/**
 * When an entrant makes a post, grant the configured one-time "post" bonus on every
 * running giveaway they've already entered. addBonus() is idempotent per source.
 */
class AwardPostBonus
{
    public function __construct(protected EntryService $entries)
    {
    }

    public function handle(Posted $event): void
    {
        $user = $event->actor;
        if (! $user || $user->isGuest()) {
            return;
        }

        $running = Giveaway::query()
            ->where('status', 'active')
            ->get()
            ->filter(fn (Giveaway $g) => $g->isRunning() && (int) ($g->settingsArray()['post_bonus'] ?? 0) > 0);

        foreach ($running as $giveaway) {
            $bonus = (int) ($giveaway->settingsArray()['post_bonus'] ?? 0);
            $this->entries->addBonus($giveaway, $user, 'post', $bonus);
        }
    }
}
