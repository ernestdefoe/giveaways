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
