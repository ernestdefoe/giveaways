<?php

namespace ErnestDefoe\Giveaways\Advent;

use Carbon\Carbon;
use ErnestDefoe\Giveaways\Giveaway;
use ErnestDefoe\Giveaways\GiveawayEntry;
use ErnestDefoe\Giveaways\GiveawayWinner;
use ErnestDefoe\Giveaways\RichText;
use ErnestDefoe\Giveaways\Slugs;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\User\User;
use Illuminate\Support\Arr;

/**
 * Saving a calendar from the builder. The calendar owns ordinary giveaways
 * that do the drawing: the grand prize (open from the first door to the end
 * of the last) and one per prize door (open for that door's day). Their
 * windows follow the calendar's dates, and they are drafts while the
 * calendar is.
 *
 * Once the first door has opened, the dates are fixed, and so is what each
 * opened door hands out (its kind, prize and number of winners): changing
 * them would change a draw people have already entered.
 */
class Calendars
{
    public function __construct(
        private readonly Clock $clock,
        private readonly RichText $rich,
        private readonly TranslatorInterface $translator,
        private readonly Exchanges $exchanges,
    ) {
    }

    /** @param array<string, mixed> $in the form's attributes */
    public function save(?Calendar $calendar, array $in, User $actor): Calendar
    {
        $calendar ??= new Calendar();
        $new = ! $calendar->exists;
        if ($new) {
            $calendar->user_id = $actor->id;
            $calendar->status = Calendar::DRAFT;
            $calendar->timezone = '';
            $calendar->days = 24;
        }
        $started = ! $new && $calendar->isPublished() && $this->clock->today($calendar) >= 1;
        $today = $started ? $this->clock->today($calendar) : 0;

        $errors = [];
        $this->schedule($calendar, $in, $new, $started, $errors);

        if ($new || array_key_exists('title', $in)) {
            $title = trim((string) ($in['title'] ?? ''));
            $title === '' ? $errors['title'] = $this->trans('title_required') : $calendar->title = mb_substr($title, 0, 255);
        }
        if (array_key_exists('description', $in)) {
            $calendar->description = $this->rich->store((string) $in['description'], $calendar, $actor);
        }
        $settings = $calendar->settingsArray();
        foreach (['minPosts' => 'min_posts', 'minAgeDays' => 'min_age_days'] as $from => $key) {
            if (array_key_exists($from, $in)) {
                $settings[$key] = max(0, min(100000, (int) $in[$from]));
            }
        }
        $calendar->settings = json_encode($settings);

        if (array_key_exists('publish', $in)) {
            if (! $in['publish'] && $started) {
                $errors['publish'] = $this->trans('unpublish_started');
            } else {
                $calendar->status = $in['publish'] ? Calendar::PUBLISHED : Calendar::DRAFT;
            }
        }

        $grand = $new ? null : $calendar->grand;
        $grandIn = $this->grandInput($grand, $in, $errors);
        $doorsIn = $this->doorsInput($calendar, $in, $today, $errors);

        if ($errors) {
            throw new ValidationException($errors);
        }

        $calendar->getConnection()->transaction(function () use ($calendar, $grand, $grandIn, $doorsIn, $actor) {
            if (! $calendar->slug || $calendar->isDirty('title')) {
                $calendar->slug = Slugs::unique(Calendar::class, $calendar->title, $calendar->id, 'advent');
            }
            $calendar->created_at ??= Carbon::now();
            $calendar->updated_at = Carbon::now();
            $calendar->save();

            $this->saveGrand($calendar, $grand ?? new Giveaway(), $grandIn);
            $this->saveDoors($calendar, $doorsIn, $actor);
        });

        return $calendar->refresh();
    }

    /** Removes the calendar with everything it owns (the database cascades too, where foreign keys are on). */
    public function delete(Calendar $calendar): void
    {
        $calendar->getConnection()->transaction(function () use ($calendar) {
            $giveaways = Giveaway::query()->where('calendar_id', $calendar->id)->pluck('id');
            Door::query()->where('calendar_id', $calendar->id)->delete();
            GiveawayEntry::query()->whereIn('giveaway_id', $giveaways)->delete();
            GiveawayWinner::query()->whereIn('giveaway_id', $giveaways)->delete();
            Giveaway::query()->whereIn('id', $giveaways)->delete();
            Opening::query()->where('calendar_id', $calendar->id)->delete();
            Reminder::query()->where('calendar_id', $calendar->id)->delete();
            $calendar->delete();
        });
    }

    /** @param array<string, string> $errors */
    private function schedule(Calendar $calendar, array $in, bool $new, bool $started, array &$errors): void
    {
        $before = [$calendar->starts_on?->format('Y-m-d'), (int) $calendar->days, (string) $calendar->timezone];

        if ($new || array_key_exists('startsOn', $in)) {
            $date = (string) ($in['startsOn'] ?? '');
            $parsed = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
            $parsed && $parsed->format('Y-m-d') === $date
                ? $calendar->starts_on = Carbon::parse($date)
                : $errors['startsOn'] = $this->trans('starts_required');
        }
        if (array_key_exists('days', $in)) {
            $days = (int) $in['days'];
            $days >= 1 && $days <= 31 ? $calendar->days = $days : $errors['days'] = $this->trans('days_range');
        }
        if (array_key_exists('timezone', $in)) {
            $zone = trim((string) $in['timezone']);
            $zone === '' || Clock::isZone($zone) ? $calendar->timezone = $zone : $errors['timezone'] = $this->trans('timezone_invalid');
        }

        $after = [$calendar->starts_on?->format('Y-m-d'), (int) $calendar->days, (string) $calendar->timezone];
        if ($started && $after !== $before) {
            $errors['startsOn'] = $this->trans('schedule_locked');
        }
    }

    /**
     * @param array<string, string> $errors
     * @return array{prize: string, winners: int, instructions: string, lines: string}
     */
    private function grandInput(?Giveaway $grand, array $in, array &$errors): array
    {
        $settings = $grand?->settingsArray() ?? [];
        $out = [
            'prize' => trim((string) ($in['grandPrize'] ?? ($grand ? $grand->prize : ''))),
            'winners' => max(1, min(100, (int) ($in['grandWinners'] ?? ($grand ? $grand->winner_count : 1)))),
            'instructions' => mb_substr(trim((string) ($in['grandClaimInstructions'] ?? $settings['claim_instructions'] ?? '')), 0, 2000),
            'lines' => mb_substr(trim((string) ($in['grandClaimLines'] ?? $settings['claim_lines'] ?? '')), 0, 20000),
        ];
        if ($out['prize'] === '') {
            $errors['grandPrize'] = $this->trans('grand_required');
        }
        if ($grand && $grand->status === 'drawn' && ($out['prize'] !== $grand->prize || $out['winners'] !== (int) $grand->winner_count)) {
            $errors['grandPrize'] = $this->translator->trans('ernestdefoe-giveaways.api.locked_after_draw');
        }
        $out['prize'] = mb_substr($out['prize'], 0, 255);

        return $out;
    }

    /**
     * The doors the form sent, checked, keyed by day. A door left out keeps
     * what it has; a day past the new last day is dropped.
     *
     * @param array<string, string> $errors
     * @return array<int, array{kind: string, title: ?string, body: ?string, prize: string, winners: int, instructions: string, lines: string, exchangeId: ?int}>
     */
    private function doorsInput(Calendar $calendar, array $in, int $today, array &$errors): array
    {
        if (! isset($in['doors']) || ! is_array($in['doors'])) {
            return [];
        }

        $existing = $calendar->exists
            ? Door::query()->where('calendar_id', $calendar->id)->with('giveaway')->get()->keyBy('day')
            : collect();
        $out = [];

        foreach (array_slice($in['doors'], 0, 31) as $row) {
            $day = (int) Arr::get((array) $row, 'day');
            if ($day < 1 || $day > $calendar->days) {
                continue;
            }
            $kind = (string) Arr::get($row, 'kind', Door::REVEAL);
            $door = [
                'kind' => in_array($kind, Door::KINDS, true) ? $kind : Door::REVEAL,
                'title' => mb_substr(trim((string) Arr::get($row, 'title', '')), 0, 255) ?: null,
                'body' => (string) Arr::get($row, 'body', ''),
                'prize' => mb_substr(trim((string) Arr::get($row, 'prize', '')), 0, 255),
                'winners' => max(1, min(100, (int) Arr::get($row, 'winners', 1))),
                'instructions' => mb_substr(trim((string) Arr::get($row, 'claimInstructions', '')), 0, 2000),
                'lines' => mb_substr(trim((string) Arr::get($row, 'claimLines', '')), 0, 20000),
                'exchangeId' => Arr::get($row, 'exchangeId') ? (int) Arr::get($row, 'exchangeId') : null,
            ];

            if ($door['kind'] === Door::PRIZE && $door['prize'] === '') {
                $errors["doors.$day.prize"] = $this->trans('door_prize_required', ['day' => $day]);
            }
            if ($door['kind'] === Door::EXCHANGE && (! $door['exchangeId'] || ! $this->exchanges->exists($door['exchangeId']))) {
                $errors["doors.$day.exchangeId"] = $this->trans('door_exchange_required', ['day' => $day]);
            }

            // An opened door's draw terms stay as they were.
            $was = $existing->get($day);
            if ($today >= $day && $was && $this->drawTerms($was) !== [$door['kind'], $door['kind'] === Door::PRIZE ? $door['prize'] : null, $door['kind'] === Door::PRIZE ? $door['winners'] : null]) {
                $errors["doors.$day"] = $this->trans('door_locked', ['day' => $day]);
            }

            $out[$day] = $door;
        }

        return $out;
    }

    private function drawTerms(Door $door): array
    {
        $prize = $door->kind === Door::PRIZE ? $door->giveaway : null;

        return [$door->kind, $prize?->prize, $prize ? (int) $prize->winner_count : null];
    }

    /** @param array{prize: string, winners: int, instructions: string, lines: string} $in */
    private function saveGrand(Calendar $calendar, Giveaway $grand, array $in): void
    {
        $grand->calendar_role = 'grand';
        $grand->title = $calendar->title;
        $grand->prize = $in['prize'];
        $grand->winner_count = $in['winners'];
        $grand->slug ??= Slugs::unique(Giveaway::class, $calendar->slug);
        $this->own($calendar, $grand, 1, $calendar->days, $in['instructions'], $in['lines']);
    }

    /** @param array<int, array<string, mixed>> $doors */
    private function saveDoors(Calendar $calendar, array $doors, User $actor): void
    {
        // Days past the last one go (only possible before the first door opens).
        $gone = Door::query()->where('calendar_id', $calendar->id)->where('day', '>', $calendar->days)->pluck('giveaway_id')->filter();
        Door::query()->where('calendar_id', $calendar->id)->where('day', '>', $calendar->days)->delete();
        Giveaway::query()->whereIn('id', $gone)->delete();

        $existing = Door::query()->where('calendar_id', $calendar->id)->with('giveaway')->get()->keyBy('day');

        // A door's draw is named after its calendar and day (winter-doors-3):
        // unique among the calendar's own, and no door's title in an address.
        // Any clash with another giveaway is found in one query, not one per door.
        $wanted = [];
        for ($day = 1; $day <= $calendar->days; $day++) {
            $wanted[$day] = mb_substr($calendar->slug, 0, 190).'-'.$day;
        }
        $taken = Giveaway::query()->whereIn('slug', $wanted)->where(fn ($q) => $q->whereNull('calendar_id')->orWhere('calendar_id', '!=', $calendar->id))->pluck('slug')->flip();

        for ($day = 1; $day <= $calendar->days; $day++) {
            $door = $existing->get($day);
            $in = $doors[$day] ?? null;
            if (! $door && ! $in) {
                continue;
            }
            if (! $door) {
                $door = new Door();
                $door->calendar_id = $calendar->id;
                $door->day = $day;
            }
            if ($in) {
                $door->kind = $in['kind'];
                $door->title = $in['title'];
                $door->body = $this->rich->store($in['body'], $calendar, $actor);
                $door->exchange_id = $in['kind'] === Door::EXCHANGE ? $in['exchangeId'] : null;
            }

            $draw = $door->giveaway;
            if ($door->kind === Door::PRIZE) {
                $draw ??= new Giveaway();
                $draw->calendar_role = 'door';
                $draw->title = $door->title ?: $this->trans('door_title', ['calendar' => $calendar->title, 'day' => $day]);
                if ($in) {
                    $draw->prize = $in['prize'];
                    $draw->winner_count = $in['winners'];
                }
                $settings = $draw->settingsArray();
                $draw->slug ??= isset($taken[$wanted[$day]]) ? Slugs::unique(Giveaway::class, $wanted[$day]) : $wanted[$day];
                $this->own($calendar, $draw, $day, $day, $in['instructions'] ?? $settings['claim_instructions'], $in['lines'] ?? $settings['claim_lines']);
                $door->giveaway_id = $draw->id;
            } elseif ($draw) {
                $door->giveaway_id = null;
            }
            $door->save();

            if ($draw && $door->kind !== Door::PRIZE) {
                $draw->delete();
            }
        }
    }

    /**
     * Keep a giveaway the calendar owns in step with it: its window runs from
     * door $from opening to the midnight after door $to, it carries the
     * calendar's eligibility rules, and it is a draft while the calendar is.
     */
    private function own(Calendar $calendar, Giveaway $giveaway, int $from, int $to, string $instructions, string $lines): void
    {
        $settings = array_merge($giveaway->settingsArray(), $calendar->settingsArray(), [
            'post_bonus' => 0,
            'claim_instructions' => $instructions,
            'claim_lines' => $lines,
        ]);

        $giveaway->calendar_id = $calendar->id;
        $giveaway->user_id = $calendar->user_id;
        $giveaway->starts_at = Carbon::instance($this->clock->opensAt($calendar, $from)->toDateTime());
        $giveaway->ends_at = Carbon::instance($this->clock->opensAt($calendar, $to + 1)->toDateTime());
        $giveaway->settings = json_encode($settings);
        if ($giveaway->status !== 'drawn') {
            $giveaway->status = $calendar->isPublished() ? 'active' : 'draft';
        }
        $giveaway->created_at ??= Carbon::now();
        $giveaway->updated_at = Carbon::now();
        $giveaway->save();
    }

    private function trans(string $key, array $params = []): string
    {
        return $this->translator->trans("ernestdefoe-giveaways.api.advent.$key", $params);
    }
}
