<?php

namespace ErnestDefoe\Giveaways;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $giveaway_id
 * @property int $user_id
 * @property int $position
 * @property \Carbon\Carbon|null $claimed_at
 */
class GiveawayWinner extends AbstractModel
{
    protected $table = 'giveaway_winners';

    public $timestamps = false;

    protected $casts = [
        'claimed_at' => 'datetime',
        'position'   => 'integer',
    ];

    public function giveaway(): BelongsTo
    {
        return $this->belongsTo(Giveaway::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
