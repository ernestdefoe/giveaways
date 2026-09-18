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
 * @property string|null $rules
 * @property string|null $cover_url
 * @property int $winner_count
 * @property string $status
 * @property \Carbon\Carbon|null $starts_at
 * @property \Carbon\Carbon $ends_at
 * @property string|null $settings
 * @property string|null $draw_seed
 * @property string|null $entrant_hash
 * @property \Carbon\Carbon|null $drawn_at
 * @property int|null $category_id
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(GiveawayCategory::class, 'category_id');
    }

    /**
     * Can $actor manage this giveaway? True for global managers, or for the
     * giveaway's own author who still holds the create permission. Centralised
     * here so the presenter and every controller share one rule.
     */
    public function canBeManagedBy(User $actor): bool
    {
        return $actor->hasPermission('giveaways.manage')
            || ($this->user_id && (int) $actor->id === (int) $this->user_id && $actor->hasPermission('giveaways.create'));
    }

    /** Decoded settings (entry methods + eligibility) with defaults. */
    public function settingsArray(): array
    {
        $s = json_decode((string) $this->settings, true) ?: [];
        return array_merge([
            'post_bonus'         => 0,   // bonus entries for posting during the window (0 = off)
            'min_posts'          => 0,
            'min_age_days'       => 0,
            'announce'           => true,
            'claim_instructions' => '',  // shown to winners when they claim their prize
            'skill_question'     => '',  // skill-testing question (empty = off)
            'skill_answer'       => '',  // its expected answer; never sent to entrants
        ], $s);
    }

    /**
     * Whether entry requires answering a skill-testing question. Where a pure
     * game of chance would be an illegal lottery (Canada, among others), a
     * correct answer is what makes entry a game of mixed skill.
     */
    public function requiresSkillAnswer(): bool
    {
        $s = $this->settingsArray();

        return trim((string) $s['skill_question']) !== '' && trim((string) $s['skill_answer']) !== '';
    }

    /**
     * Check an entrant's answer leniently: case, surrounding whitespace, a
     * trailing full stop and thousands separators are ignored, and two numeric
     * answers are compared as numbers so "7.0" matches "7".
     */
    public function skillAnswerMatches(?string $given): bool
    {
        if (! $this->requiresSkillAnswer()) {
            return true;
        }

        $expected = self::normalizeAnswer((string) $this->settingsArray()['skill_answer']);
        $actual = self::normalizeAnswer((string) $given);
        if ($actual === '') {
            return false;
        }

        if (is_numeric($expected) && is_numeric($actual)) {
            return abs((float) $expected - (float) $actual) < 0.000001;
        }

        return $expected === $actual;
    }

    private static function normalizeAnswer(string $value): string
    {
        $value = rtrim(trim(mb_strtolower($value)), '.');

        return (string) preg_replace('/[\s,]+/u', '', $value);
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
