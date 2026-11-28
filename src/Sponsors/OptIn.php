<?php

namespace ErnestDefoe\Giveaways\Sponsors;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's consent to share their email address with a sponsor, for one
 * campaign, with the sentence they agreed to.
 *
 * @property int $id
 * @property int $sponsor_id
 * @property int $user_id
 * @property string $campaign giveaway|calendar
 * @property int $campaign_id
 * @property string $consent_text
 * @property \Carbon\Carbon $opted_in_at
 * @property-read Sponsor|null $sponsor
 * @property-read User|null $user
 */
class OptIn extends AbstractModel
{
    public const GIVEAWAY = 'giveaway';
    public const CALENDAR = 'calendar';

    protected $table = 'giveaway_sponsor_optins';

    public $timestamps = false;

    protected $casts = [
        'id' => 'integer',
        'sponsor_id' => 'integer',
        'user_id' => 'integer',
        'campaign_id' => 'integer',
        'opted_in_at' => 'datetime',
    ];

    /**
     * Which of these campaigns the member shares their email with the
     * campaign's CURRENT sponsor: an agreement with a sponsor the campaign
     * no longer has doesn't tick the box for the new one. One query.
     *
     * @param iterable<\ErnestDefoe\Giveaways\Giveaway|\ErnestDefoe\Giveaways\Advent\Calendar> $campaigns
     * @return array<int, true> campaign id => true
     */
    public static function sharedBy(?User $actor, string $campaign, iterable $campaigns): array
    {
        $sponsorOf = [];
        foreach ($campaigns as $c) {
            if ($c->sponsor_id) {
                $sponsorOf[(int) $c->id] = (int) $c->sponsor_id;
            }
        }
        if (! $actor || $actor->isGuest() || ! $sponsorOf) {
            return [];
        }

        $shared = [];
        foreach (self::query()->where('user_id', $actor->id)->where('campaign', $campaign)->whereIn('campaign_id', array_keys($sponsorOf))->get(['campaign_id', 'sponsor_id']) as $row) {
            if (($sponsorOf[$row->campaign_id] ?? null) === $row->sponsor_id) {
                $shared[$row->campaign_id] = true;
            }
        }

        return $shared;
    }

    /** @return BelongsTo<Sponsor, $this> */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
