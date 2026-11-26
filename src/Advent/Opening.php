<?php

namespace ErnestDefoe\Giveaways\Advent;

use Flarum\Database\AbstractModel;

/**
 * A member opened a door: one grand-prize ticket.
 *
 * @property int $id
 * @property int $calendar_id
 * @property int $user_id
 * @property int $day
 * @property bool $via_joker
 * @property \Carbon\Carbon $opened_at
 */
class Opening extends AbstractModel
{
    protected $table = 'giveaway_calendar_opens';

    public $timestamps = false;

    protected $casts = [
        'id' => 'integer',
        'calendar_id' => 'integer',
        'user_id' => 'integer',
        'day' => 'integer',
        'via_joker' => 'boolean',
        'opened_at' => 'datetime',
    ];
}
