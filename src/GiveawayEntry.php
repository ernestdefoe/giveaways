<?php

namespace ErnestDefoe\Giveaways;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $giveaway_id
 * @property int $user_id
 * @property int $entries
 * @property string|null $sources
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read Giveaway|null $giveaway
 * @property-read User|null $user
 */
class GiveawayEntry extends AbstractModel
{
    protected $table = 'giveaway_entries';

    protected $casts = ['entries' => 'integer'];

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

    public function sourcesArray(): array
    {
        return json_decode((string) $this->sources, true) ?: [];
    }
}
