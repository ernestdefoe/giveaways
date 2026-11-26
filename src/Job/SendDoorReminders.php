<?php

namespace ErnestDefoe\Giveaways\Job;

use ErnestDefoe\Giveaways\Advent\Calendar;
use ErnestDefoe\Giveaways\Advent\Clock;
use ErnestDefoe\Giveaways\Advent\Opening;
use ErnestDefoe\Giveaways\Advent\Reminder;
use ErnestDefoe\Giveaways\Notification\DoorOpenBlueprint;
use Flarum\Notification\NotificationSyncer;
use Flarum\Queue\AbstractJob;
use Flarum\User\User;

/**
 * Tells the members who asked that today's door is open, 200 at a time.
 * Members who have already opened it are skipped, as is everyone once the day
 * is over (a queue that ran late doesn't send yesterday's alert).
 *
 * Chunks go to the notification drivers directly: NotificationSyncer::sync()
 * treats its list as the complete set of recipients and hides the alert from
 * everyone in the earlier chunks. Core's SendNotificationsJob still drops
 * anyone who already has the alert.
 */
class SendDoorReminders extends AbstractJob
{
    public const CHUNK = 200;

    public function __construct(public int $calendarId, public int $day)
    {
    }

    public function handle(Clock $clock): void
    {
        $calendar = Calendar::query()->whereKey($this->calendarId)->where('status', Calendar::PUBLISHED)->first();
        if (! $calendar || $clock->today($calendar) !== $this->day) {
            return;
        }

        $blueprint = new DoorOpenBlueprint($calendar, $this->day);

        User::query()
            ->whereIn('id', Reminder::query()->where('calendar_id', $calendar->id)->select('user_id'))
            ->whereNotIn('id', Opening::query()->where('calendar_id', $calendar->id)->where('day', $this->day)->select('user_id'))
            ->where('is_email_confirmed', true)
            ->chunkById(self::CHUNK, function ($users) use ($blueprint) {
                foreach (NotificationSyncer::getNotificationDrivers() as $driver) {
                    $driver->send($blueprint, $users->all());
                }
            });
    }
}
