<?php

namespace ErnestDefoe\Giveaways\Sponsors;

use Carbon\Carbon;
use Illuminate\Contracts\Cache\Repository as Cache;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Views and outbound clicks for a sponsor's campaign, as daily totals. A
 * visit is counted once per session per campaign per half hour, through a
 * short-lived cache key made from a hash of the session id. Nothing about
 * the visitor reaches the database.
 */
class Counter
{
    private const WINDOW = 1800;

    public function __construct(private readonly Cache $cache)
    {
    }

    /** @param 'views'|'clicks' $kind */
    public function record(int $sponsorId, string $campaign, int $campaignId, string $kind, ServerRequestInterface $request): void
    {
        $session = $request->getAttribute('session');
        $sid = $session && method_exists($session, 'getId') ? (string) $session->getId() : '';
        if ($sid !== '' && ! $this->cache->add('ernestdefoe-giveaways.sponsor.'.hash('sha256', "$kind|$sid|$campaign|$campaignId"), 1, self::WINDOW)) {
            return;
        }

        // Today's row, made once (a date string, stored the same on every driver), then counted
        // in one atomic UPDATE: two visits at once can't lose a count or trip the unique key.
        $day = Carbon::now()->toDateString();
        Stat::query()->insertOrIgnore(['sponsor_id' => $sponsorId, 'campaign' => $campaign, 'campaign_id' => $campaignId, 'day' => $day, 'views' => 0, 'clicks' => 0]);
        Stat::query()->where('sponsor_id', $sponsorId)->where('campaign', $campaign)->where('campaign_id', $campaignId)->where('day', $day)->increment($kind);
    }
}
