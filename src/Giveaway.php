<?php

namespace ErnestDefoe\Giveaways;

use Carbon\Carbon;
use ErnestDefoe\Giveaways\Advent\Calendar;
use ErnestDefoe\Giveaways\Sponsors\Sponsor;
use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
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
 * @property int|null $post_id
 * @property int|null $discussion_id
 * @property int|null $calendar_id
 * @property string|null $calendar_role
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, GiveawayEntry> $entries
 * @property-read \Illuminate\Database\Eloquent\Collection<int, GiveawayWinner> $winners
 * @property int|null $sponsor_id
 * @property-read User|null $user
 * @property-read Sponsor|null $sponsor
 * @property-read GiveawayCategory|null $category
 * @property-read Calendar|null $calendar
 */
class Giveaway extends AbstractModel
{
    // Who sees which giveaway: ScopeGiveawayVisibility, registered in extend.php.
    use ScopeVisibilityTrait;

    protected $table = 'giveaways';

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'drawn_at' => 'datetime',
        'winner_count' => 'integer',
        'calendar_id' => 'integer',
        'sponsor_id' => 'integer',
    ];

    /** @return BelongsTo<Calendar, $this> */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class, 'calendar_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** @return HasMany<GiveawayEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(GiveawayEntry::class);
    }

    /** @return HasMany<GiveawayWinner, $this> */
    public function winners(): HasMany
    {
        return $this->hasMany(GiveawayWinner::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<GiveawayCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(GiveawayCategory::class, 'category_id');
    }

    /**
     * Can $actor manage this giveaway? True for global managers, or for the
     * giveaway's own author who still holds the create permission, unless it
     * belongs to an advent calendar. Centralised
     * here so the presenter and every controller share one rule.
     */
    public function canBeManagedBy(User $actor): bool
    {
        // A calendar's giveaways are managed through the calendar, never one by one.
        if ($this->calendar_id) {
            return false;
        }

        return $actor->hasPermission('giveaways.manage')
            || ($this->user_id && (int) $actor->id === (int) $this->user_id && $actor->hasPermission('giveaways.create'));
    }

    /**
     * Can $actor run the draw right now? A giveaway's author may only draw once
     * it has ended; closing it early against a small pool is a manager's call.
     */
    public function canBeDrawnBy(User $actor): bool
    {
        return $this->status === 'active'
            && $this->canBeManagedBy($actor)
            && ($actor->hasPermission('giveaways.manage') || $this->hasEnded());
    }

    /**
     * Can $actor delete this giveaway? Once drawn it is the record of who won,
     * so only a manager may remove it; the author can no longer erase it.
     */
    public function canBeDeletedBy(User $actor): bool
    {
        return $this->canBeManagedBy($actor)
            && (in_array($this->status, ['active', 'draft'], true) || $actor->hasPermission('giveaways.manage'));
    }

    /** Decoded settings (entry methods + eligibility) with defaults. */
    public function settingsArray(): array
    {
        $s = json_decode((string) $this->settings, true) ?: [];

        return array_merge([
            'post_bonus' => 0,   // bonus entries for posting during the window (0 = off)
            'min_posts' => 0,
            'min_age_days' => 0,
            'announce' => true,
            'claim_instructions' => '',  // shown to every winner once they claim
            'claim_lines' => '',  // one per line: winner #1 gets line 1, and so on
            'skill_question' => '',  // skill-testing question (empty = off)
            'skill_answer' => '',  // its expected answer; never sent to entrants
            'skill_attempts' => 0,   // wrong answers allowed before forfeit (0 = unlimited)
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
     * How many wrong answers the winner may give before forfeiting the prize.
     * 0 means unlimited — they can keep trying and nothing is redrawn.
     */
    public function skillAttemptLimit(): int
    {
        return $this->requiresSkillAnswer() ? max(0, (int) $this->settingsArray()['skill_attempts']) : 0;
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

    /**
     * The line of the per-winner list that belongs to the winner in this
     * position, or null when the list is shorter than the number of winners.
     * Another winner's line is never handed out in its place.
     */
    public function claimLineFor(int $position): ?string
    {
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\R/u', (string) $this->settingsArray()['claim_lines']) ?: []),
            'strlen'
        ));

        return $lines[$position - 1] ?? null;
    }

    /** Whether people can view who has entered: the host, managers, or the permission. */
    public function participantsVisibleTo(User $actor): bool
    {
        return ! $actor->isGuest() && (
            ($this->user_id && (int) $actor->id === (int) $this->user_id)
            || $actor->hasPermission('giveaways.viewParticipants')
            || $actor->hasPermission('giveaways.manage')
        );
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

    /** @return BelongsTo<Sponsor, $this> */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }
}
