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
 * @property int $skill_attempts
 * @property \Carbon\Carbon|null $forfeited_at
 */
class GiveawayWinner extends AbstractModel
{
    protected $table = 'giveaway_winners';

    public $timestamps = false;

    protected $casts = [
        'claimed_at'     => 'datetime',
        'forfeited_at'   => 'datetime',
        'position'       => 'integer',
        'skill_attempts' => 'integer',
    ];

    /** A forfeited row is history: it never claims, and never wins again. */
    public function isForfeited(): bool
    {
        return $this->forfeited_at !== null;
    }

    public function giveaway(): BelongsTo
    {
        return $this->belongsTo(Giveaway::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
