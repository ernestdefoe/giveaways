<?php

namespace ErnestDefoe\Giveaways;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $color
 * @property string|null $icon
 * @property int $position
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class GiveawayCategory extends AbstractModel
{
    protected $table = 'giveaway_categories';

    protected $casts = [
        'position' => 'integer',
    ];

    /** @return HasMany<Giveaway, $this> */
    public function giveaways(): HasMany
    {
        return $this->hasMany(Giveaway::class, 'category_id');
    }
}
