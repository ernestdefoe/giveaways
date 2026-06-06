<?php

namespace ErnestDefoe\Giveaways;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $title
 * @property string $slug
 * @property string $prize
 * @property string|null $description
 * @property string|null $cover_url
 * @property int $winner_count
 * @property string $status
 * @property \Carbon\Carbon|null $starts_at
 * @property \Carbon\Carbon $ends_at
 * @property string|null $settings
 * @property string|null $draw_seed
 * @property string|null $entrant_hash
 * @property \Carbon\Carbon|null $drawn_at
 * @property int|null $discussion_id
 */
class Giveaway extends AbstractModel
{
    protected $table = 'giveaways';

    protected $casts = [
        'starts_at'    => 'datetime',
        'ends_at'      => 'datetime',
        'drawn_at'     => 'datetime',
        'winner_count' => 'integer',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(GiveawayEntry::class);
    }

    public function winners(): HasMany
    {
        return $this->hasMany(GiveawayWinner::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Decoded settings (entry methods + eligibility) with defaults. */
    public function settingsArray(): array
    {
        $s = json_decode((string) $this->settings, true) ?: [];
        return array_merge([
            'post_bonus'    => 0,   // bonus entries for posting during the window (0 = off)
            'min_posts'     => 0,
            'min_age_days'  => 0,
            'announce'      => true,
        ], $s);
    }

    public function isRunning(): bool
    {
        $now = Carbon::now();
        return $this->status === 'active'
            && (! $this->starts_at || $this->starts_at->lte($now))
            && $this->ends_at->gt($now);
    }

    public function hasEnded(): bool
    {
        return $this->ends_at->lte(Carbon::now());
    }
}
