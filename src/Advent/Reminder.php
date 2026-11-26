<?php

namespace ErnestDefoe\Giveaways\Advent;

use Flarum\Database\AbstractModel;

/**
 * A member asked to be told when each door opens.
 *
 * @property int $calendar_id
 * @property int $user_id
 */
class Reminder extends AbstractModel
{
    protected $table = 'giveaway_calendar_reminders';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $casts = ['calendar_id' => 'integer', 'user_id' => 'integer'];
}
