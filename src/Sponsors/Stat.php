<?php

namespace ErnestDefoe\Giveaways\Sponsors;

use Flarum\Database\AbstractModel;

/**
 * One campaign's views and outbound clicks on one day.
 *
 * @property int $id
 * @property int $sponsor_id
 * @property string $campaign
 * @property int $campaign_id
 * @property \Carbon\Carbon $day
 * @property int $views
 * @property int $clicks
 */
class Stat extends AbstractModel
{
    protected $table = 'giveaway_sponsor_stats';

    public $timestamps = false;

    protected $casts = ['id' => 'integer', 'sponsor_id' => 'integer', 'campaign_id' => 'integer', 'day' => 'date', 'views' => 'integer', 'clicks' => 'integer'];
}
