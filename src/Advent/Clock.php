<?php

namespace ErnestDefoe\Giveaways\Advent;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

/**
 * What day it is for a calendar. Door N opens at midnight of starts_on + N - 1
 * in the calendar's timezone. Days are counted on calendar dates, not in
 * 24-hour steps, so a daylight-saving change never shifts a door.
 */
class Clock
{
    public function __construct(private readonly ForumZone $forumZone)
    {
    }

    /** The calendar's own timezone, or the forum's. */
    public function zone(Calendar $calendar): string
    {
        $own = (string) $calendar->timezone;

        return $own !== '' && self::isZone($own) ? $own : $this->forumZone->name();
    }

    public static function isZone(string $zone): bool
    {
        return in_array($zone, timezone_identifiers_list(), true);
    }

    /** When door $day opens (day $days + 1 = when the calendar closes), in UTC. */
    public function opensAt(Calendar $calendar, int $day): CarbonImmutable
    {
        return CarbonImmutable::parse($calendar->starts_on->format('Y-m-d'), $this->zone($calendar))
            ->addDays($day - 1)
            ->utc();
    }

    /**
     * Today's door number: 0 or less before the first door, above `days` once
     * the last one has passed.
     */
    public function today(Calendar $calendar, ?Carbon $now = null): int
    {
        $local = ($now ?? Carbon::now())->copy()->setTimezone($this->zone($calendar))->format('Y-m-d');
        $start = new \DateTimeImmutable($calendar->starts_on->format('Y-m-d'));
        $diff = $start->diff(new \DateTimeImmutable($local));

        return ($diff->invert ? -$diff->days : $diff->days) + 1;
    }
}
