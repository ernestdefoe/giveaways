<?php

namespace ErnestDefoe\Giveaways\Console;

use ErnestDefoe\Giveaways\Advent\Calendar;
use ErnestDefoe\Giveaways\Advent\Clock;
use ErnestDefoe\Giveaways\Job\SendDoorReminders;
use Flarum\Console\AbstractCommand;
use Illuminate\Contracts\Queue\Queue;

/**
 * Queues today's "door is open" alerts, once per calendar per door. Runs every
 * five minutes, so a door opening at a half-hour midnight is caught too.
 */
class RemindCommand extends AbstractCommand
{
    public function __construct(private readonly Clock $clock, private readonly Queue $queue)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('giveaways:advent-reminders')
            ->setDescription('Queue the alerts for advent calendar doors that have just opened.');
    }

    protected function fire(): int
    {
        Calendar::query()
            ->where('status', Calendar::PUBLISHED)
            ->whereColumn('reminded_day', '<', 'days')
            ->each(function (Calendar $calendar) {
                $today = $this->clock->today($calendar);
                if ($today < 1 || $today > $calendar->days || $calendar->reminded_day >= $today) {
                    return;
                }
                // Claimed with a conditional update, so two overlapping runs can't both queue it.
                $claimed = Calendar::query()->whereKey($calendar->id)->where('reminded_day', '<', $today)->update(['reminded_day' => $today]);
                if ($claimed) {
                    $this->queue->push(new SendDoorReminders($calendar->id, $today));
                    $this->info("Queued door $today of calendar #{$calendar->id}");
                }
            });

        return 0;
    }
}
