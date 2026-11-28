<?php

namespace ErnestDefoe\Giveaways\Sponsors;

use Carbon\Carbon;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\UniqueConstraintViolationException;
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

        $day = Carbon::now()->toDateString();
        $row = Stat::query()->where('sponsor_id', $sponsorId)->where('campaign', $campaign)->where('campaign_id', $campaignId)->where('day', $day)->first();
        if (! $row) {
            $row = new Stat();
            $row->sponsor_id = $sponsorId;
            $row->campaign = $campaign;
            $row->campaign_id = $campaignId;
            $row->day = Carbon::parse($day);
            $row->views = 0;
            $row->clicks = 0;
            try {
                $row->save();
            } catch (UniqueConstraintViolationException $e) {
                // Another visit made today's row first: count on that one.
                $row = Stat::query()->where('sponsor_id', $sponsorId)->where('campaign', $campaign)->where('campaign_id', $campaignId)->where('day', $day)->firstOrFail();
            }
        }
        $row->increment($kind);
    }
}
