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
 * @property \Carbon\Carbon|null $created_at
 * @property-read Giveaway|null $giveaway
 * @property-read User|null $user
 */
class GiveawayWinner extends AbstractModel
{
    protected $table = 'giveaway_winners';

    public $timestamps = false;

    protected $casts = [
        'claimed_at' => 'datetime',
        'forfeited_at' => 'datetime',
        'position' => 'integer',
        'skill_attempts' => 'integer',
    ];

    /** A forfeited row is history: it never claims, and never wins again. */
    public function isForfeited(): bool
    {
        return $this->forfeited_at !== null;
    }

    /** @return BelongsTo<Giveaway, $this> */
    public function giveaway(): BelongsTo
    {
        return $this->belongsTo(Giveaway::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
