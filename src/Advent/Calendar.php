<?php

namespace ErnestDefoe\Giveaways\Advent;

use ErnestDefoe\Giveaways\Giveaway;
use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An advent calendar: `days` doors from starts_on, each opening at midnight in
 * the calendar's timezone (see Clock).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property \Carbon\Carbon $starts_on
 * @property int $days
 * @property string $timezone
 * @property string $status
 * @property string|null $settings
 * @property int $reminded_day
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Door> $doors
 * @property-read Giveaway|null $grand
 * @property-read User|null $user
 */
class Calendar extends AbstractModel
{
    // Who sees which calendar: ScopeCalendarVisibility, registered in extend.php.
    use ScopeVisibilityTrait;

    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';

    protected $table = 'giveaway_calendars';

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'starts_on' => 'date',
        'days' => 'integer',
        'reminded_day' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function isPublished(): bool
    {
        return $this->status === self::PUBLISHED;
    }

    /** @return HasMany<Door, $this> */
    public function doors(): HasMany
    {
        return $this->hasMany(Door::class, 'calendar_id')->orderBy('day');
    }

    /** @return HasOne<Giveaway, $this> */
    public function grand(): HasOne
    {
        return $this->hasOne(Giveaway::class, 'calendar_id')->where('calendar_role', 'grand');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Eligibility copied onto the calendar's giveaways, with defaults. */
    public function settingsArray(): array
    {
        return array_merge(['min_posts' => 0, 'min_age_days' => 0], json_decode((string) $this->settings, true) ?: []);
    }
}
