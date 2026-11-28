<?php

namespace ErnestDefoe\Giveaways\Sponsors;

use Flarum\Database\AbstractModel;

/**
 * The log of a sponsor's opt-in list being exported: who, when, how many rows.
 *
 * @property int $id
 * @property int $sponsor_id
 * @property int|null $user_id
 * @property int $rows
 * @property \Carbon\Carbon $created_at
 */
class Export extends AbstractModel
{
    protected $table = 'giveaway_sponsor_exports';

    public $timestamps = false;

    protected $casts = ['id' => 'integer', 'sponsor_id' => 'integer', 'user_id' => 'integer', 'rows' => 'integer', 'created_at' => 'datetime'];
}
