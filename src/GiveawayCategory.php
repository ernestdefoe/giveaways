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
 */
class GiveawayCategory extends AbstractModel
{
    protected $table = 'giveaway_categories';

    protected $casts = [
        'position' => 'integer',
    ];

    public function giveaways(): HasMany
    {
        return $this->hasMany(Giveaway::class, 'category_id');
    }
}
