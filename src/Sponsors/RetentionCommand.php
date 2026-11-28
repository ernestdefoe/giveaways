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

        // Driven from the agreements themselves: a campaign whose sponsor was
        // since removed, or that no longer exists, is purged all the same.
        $erased = 0;
        foreach ([OptIn::GIVEAWAY, OptIn::CALENDAR] as $campaign) {
            $ids = OptIn::query()->where('campaign', $campaign)->distinct()->pluck('campaign_id')->map(fn ($id) => (int) $id)->all();
            foreach (array_chunk($ids, 200) as $chunk) {
                $over = array_values(array_diff($chunk, $this->stillRunning($campaign, $chunk, $cutoff)));
                if ($over) {
                    $erased += OptIn::query()->where('campaign', $campaign)->whereIn('campaign_id', $over)->delete();
                }
            }
        }
        $this->info("Erased $erased opt-ins.");

        return 0;
    }

    /**
     * Of these campaigns, the ones still running or that ended after the
     * cutoff (kept). Every other id has been over long enough, or is gone.
     *
     * @param list<int> $ids
     * @return list<int>
     */
    private function stillRunning(string $campaign, array $ids, Carbon $cutoff): array
    {
        if ($campaign === OptIn::GIVEAWAY) {
            return Giveaway::query()->whereIn('id', $ids)->where('ends_at', '>', $cutoff)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return Calendar::query()->whereIn('id', $ids)->get()
            ->filter(fn (Calendar $c) => $this->clock->opensAt($c, $c->days + 1) > $cutoff)
            ->map(fn (Calendar $c) => (int) $c->id)->values()->all();
    }
}
