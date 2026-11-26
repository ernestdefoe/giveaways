<?php

namespace ErnestDefoe\Giveaways\Advent;

use ErnestDefoe\Giveaways\Giveaway;
use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One door of a calendar.
 *
 * @property int $id
 * @property int $calendar_id
 * @property int $day
 * @property string $kind
 * @property string|null $title
 * @property string|null $body
 * @property int|null $giveaway_id
 * @property int|null $exchange_id
 * @property-read Calendar|null $calendar
 * @property-read Giveaway|null $giveaway
 */
class Door extends AbstractModel
{
    /** Opening it enters that day's draw. */
    public const PRIZE = 'prize';
    /** A perk only the members who opened it see. */
    public const REVEAL = 'reveal';
    /** A Secret Santa exchange to sign up for. */
    public const EXCHANGE = 'exchange';

    public const KINDS = [self::PRIZE, self::REVEAL, self::EXCHANGE];

    protected $table = 'giveaway_calendar_doors';

    public $timestamps = false;

    protected $casts = [
        'id' => 'integer',
        'calendar_id' => 'integer',
        'day' => 'integer',
        'giveaway_id' => 'integer',
        'exchange_id' => 'integer',
    ];

    /** @return BelongsTo<Calendar, $this> */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class, 'calendar_id');
    }

    /** @return BelongsTo<Giveaway, $this> */
    public function giveaway(): BelongsTo
    {
        return $this->belongsTo(Giveaway::class);
    }
}
