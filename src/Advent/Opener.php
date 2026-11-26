<?php

namespace ErnestDefoe\Giveaways\Advent;

use Carbon\Carbon;
use ErnestDefoe\Giveaways\EntryService;
use ErnestDefoe\Giveaways\GiveawayEntry;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Opening a door. Today's door is free; a past door needs one of the member's
 * jokers; a future door can't be opened at all. Every opened door is one
 * ticket for the grand prize, and opening a prize door on its day enters that
 * day's draw. A door opened late with a joker is still a ticket, but its draw
 * may already have run, so it never enters one.
 */
class Opener
{
    public const JOKERS = 'ernestdefoe-giveaways.advent_jokers';

    public function __construct(
        private readonly Clock $clock,
        private readonly EntryService $entries,
        private readonly TranslatorInterface $translator,
        private readonly SettingsRepositoryInterface $settings,
        private readonly Cache $cache,
    ) {
    }

    /** Jokers each member gets per calendar (an admin setting, 0–3). */
    public function jokers(): int
    {
        return max(0, min(3, (int) $this->settings->get(self::JOKERS)));
    }

    public function open(Calendar $calendar, User $actor, int $day, bool $joker): void
    {
        $actor->assertRegistered();
        $actor->assertCan('giveaways.enter');

        $grand = $calendar->grand;
        if (! $calendar->isPublished() || ! $grand) {
            $this->fail('closed');
        }
        if ($reason = $this->entries->ineligibleReason($grand, $actor)) {
            throw new ValidationException(['door' => $reason]);
        }

        $today = $this->clock->today($calendar);
        if ($day < 1 || $day > $calendar->days || $day > $today) {
            $this->fail('not_yet');
        }
        $late = $day < $today;
        if ($late && ! $joker) {
            $this->fail('missed');
        }

        // One member's two doors at once (a joker and today's) would each count
        // the tickets before the other's row landed. A lock per member and
        // calendar, never waited on: the second click is told to try again.
        $lock = $this->cache->lock("ernestdefoe-giveaways.advent.{$calendar->id}.{$actor->id}", 10);
        if (! $lock->get()) {
            $this->fail('busy');
        }

        try {
            $calendar->getConnection()->transaction(fn () => $this->record($calendar, $actor, $day, $late));
        } finally {
            $lock->release();
        }
    }

    private function record(Calendar $calendar, User $actor, int $day, bool $late): void
    {
        $mine = Opening::query()->where('calendar_id', $calendar->id)->where('user_id', $actor->id);
        if ((clone $mine)->where('day', $day)->exists()) {
            return; // already open: nothing to spend, nothing to count again
        }
        if ($late && (clone $mine)->where('via_joker', true)->count() >= $this->jokers()) {
            $this->fail('no_jokers');
        }

        $opening = new Opening();
        $opening->calendar_id = $calendar->id;
        $opening->user_id = $actor->id;
        $opening->day = $day;
        $opening->via_joker = $late;
        $opening->opened_at = Carbon::now();
        $opening->save();

        $this->countTickets($calendar, $actor, (clone $mine)->count());

        $door = Door::query()->where('calendar_id', $calendar->id)->where('day', $day)->with('giveaway')->first();
        $draw = $door?->kind === Door::PRIZE ? $door->giveaway : null;
        if (! $late && $draw && $this->entries->ineligibleReason($draw, $actor) === null) {
            $this->entries->enter($draw, $actor);
        }
    }

    /** The member's grand-prize entry: one entry per door opened. */
    private function countTickets(Calendar $calendar, User $actor, int $tickets): void
    {
        /** @var \ErnestDefoe\Giveaways\Giveaway $grand */
        $grand = $calendar->grand;
        $entry = GiveawayEntry::query()->where('giveaway_id', $grand->id)->where('user_id', $actor->id)->first();
        if (! $entry) {
            $entry = new GiveawayEntry();
            $entry->giveaway_id = $grand->id;
            $entry->user_id = $actor->id;
            $entry->created_at = Carbon::now();
        }
        $entry->sources = json_encode(['doors' => $tickets]);
        $entry->entries = $tickets;
        $entry->updated_at = Carbon::now();
        $entry->save();
    }

    private function fail(string $key): never
    {
        throw new ValidationException(['door' => $this->translator->trans("ernestdefoe-giveaways.api.advent.$key")]);
    }
}
