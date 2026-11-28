<?php

namespace ErnestDefoe\Giveaways\Sponsors;

use Carbon\Carbon;
use ErnestDefoe\Giveaways\Advent\Calendar;
use ErnestDefoe\Giveaways\Advent\Clock;
use ErnestDefoe\Giveaways\Giveaway;
use Flarum\Console\AbstractCommand;
use Flarum\Settings\SettingsRepositoryInterface;

/**
 * Erases the opt-ins of campaigns that ended more than the retention period
 * ago (a setting, 0 to 365 days; 0 erases them as soon as a campaign ends).
 * Runs daily; deletes in chunks.
 */
class RetentionCommand extends AbstractCommand
{
    public const DAYS = 'ernestdefoe-giveaways.sponsor_retention_days';

    public function __construct(private readonly SettingsRepositoryInterface $settings, private readonly Clock $clock)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('giveaways:sponsor-retention')
            ->setDescription("Erase sponsors' opt-ins once a campaign has been over for the retention period.");
    }

    protected function fire(): int
    {
        $cutoff = Carbon::now()->subDays(max(0, min(365, (int) $this->settings->get(self::DAYS))));

        $giveaways = Giveaway::query()->whereNotNull('sponsor_id')->where('ends_at', '<=', $cutoff)->pluck('id')->all();
        $calendars = Calendar::query()->whereNotNull('sponsor_id')->get()
            ->filter(fn (Calendar $c) => $this->clock->opensAt($c, $c->days + 1) <= $cutoff)
            ->map(fn (Calendar $c) => $c->id)->values()->all();

        $erased = 0;
        foreach ([OptIn::GIVEAWAY => $giveaways, OptIn::CALENDAR => $calendars] as $campaign => $ids) {
            foreach (array_chunk($ids, 200) as $chunk) {
                $erased += OptIn::query()->where('campaign', $campaign)->whereIn('campaign_id', $chunk)->delete();
            }
        }
        $this->info("Erased $erased opt-ins.");

        return 0;
    }
}
