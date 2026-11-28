<?php

namespace ErnestDefoe\Giveaways\Sponsors;

use Flarum\Database\AbstractModel;

/**
 * A sponsor of giveaways and advent calendars.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $logo_url
 * @property string $website_url
 * @property string $privacy_url
 * @property string|null $description
 * @property string|null $report_token_hash
 * @property \Carbon\Carbon|null $report_token_created_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Sponsor extends AbstractModel
{
    protected $table = 'giveaway_sponsors';

    protected $casts = [
        'id' => 'integer',
        'report_token_created_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
