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
 */
class GiveawayEntry extends AbstractModel
{
    protected $table = 'giveaway_entries';

    protected $casts = ['entries' => 'integer'];

    public function giveaway(): BelongsTo
    {
        return $this->belongsTo(Giveaway::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourcesArray(): array
    {
        return json_decode((string) $this->sources, true) ?: [];
    }
}
