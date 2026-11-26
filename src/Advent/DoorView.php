<?php

namespace ErnestDefoe\Giveaways\Advent;

use ErnestDefoe\Giveaways\RichText;
use Flarum\User\User;

/**
 * The doors as one member sees them. This is the only place a door's content
 * leaves the server for a member, and it hands it out only for doors that
 * member has opened: a door that hasn't opened yet, or that they haven't
 * opened, is a number, a state and the time it opens. Nothing else.
 *
 * States: locked (its day hasn't come), ready (today's, not yet opened),
 * missed (a past day not opened), opened.
 */
class DoorView
{
    public function __construct(private readonly Clock $clock, private readonly RichText $rich)
    {
    }

    /**
     * @param array<int, bool> $opened day => opened with a joker
     * @param array<int, Door> $doors day => door, giveaway loaded (only opened days are needed)
     * @param array<int, array<string, mixed>> $exchanges id => exchange the actor may see
     * @param array<int, true> $entered ids of the door draws the actor is in
     * @return list<array<string, mixed>>
     */
    public function forMember(Calendar $calendar, array $opened, array $doors, array $exchanges, array $entered): array
    {
        $today = $this->clock->today($calendar);
        $out = [];

        for ($day = 1; $day <= $calendar->days; $day++) {
            $row = [
                'day' => $day,
                'opensAt' => $this->clock->opensAt($calendar, $day)->toIso8601String(),
                'state' => match (true) {
                    isset($opened[$day]) => 'opened',
                    $day > $today => 'locked',
                    $day === $today => 'ready',
                    default => 'missed',
                },
            ];

            if (isset($opened[$day])) {
                $row += $this->content($calendar, $doors[$day] ?? null, $opened[$day], $exchanges, $entered);
            }

            $out[] = $row;
        }

        return $out;
    }

    /**
     * Everything about every door, for the builder. Managers only.
     *
     * @param iterable<Door> $doors giveaway loaded
     * @return list<array<string, mixed>>
     */
    public function forEditor(Calendar $calendar, iterable $doors): array
    {
        $out = [];
        foreach ($doors as $door) {
            $draw = $door->kind === Door::PRIZE ? $door->giveaway : null;
            $settings = $draw?->settingsArray() ?? [];
            $out[] = [
                'day' => $door->day,
                'kind' => $door->kind,
                'title' => $door->title,
                'body' => $this->rich->source($door->body, $calendar),
                'prize' => $draw?->prize,
                'winners' => $draw ? (int) $draw->winner_count : 1,
                'claimInstructions' => $settings['claim_instructions'] ?? '',
                'claimLines' => $settings['claim_lines'] ?? '',
                'exchangeId' => $door->exchange_id,
            ];
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $exchanges
     * @param array<int, true> $entered
     */
    private function content(Calendar $calendar, ?Door $door, bool $joker, array $exchanges, array $entered): array
    {
        $kind = $door->kind ?? Door::REVEAL;
        $draw = $kind === Door::PRIZE ? $door?->giveaway : null;

        return [
            'joker' => $joker,
            'kind' => $kind,
            'title' => $door?->title,
            'html' => $this->rich->html($door?->body, $calendar),
            'draw' => $draw ? [
                'slug' => $draw->slug,
                'prize' => $draw->prize,
                'winnerCount' => (int) $draw->winner_count,
                'status' => $draw->status,
                'entered' => isset($entered[$draw->id]),
            ] : null,
            'exchange' => $kind === Door::EXCHANGE && $door?->exchange_id ? ($exchanges[$door->exchange_id] ?? null) : null,
        ];
    }
}
