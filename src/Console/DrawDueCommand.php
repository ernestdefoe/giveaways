<?php

namespace ErnestDefoe\Giveaways\Console;

use Carbon\Carbon;
use ErnestDefoe\Giveaways\DrawService;
use ErnestDefoe\Giveaways\Giveaway;
use Flarum\Console\AbstractCommand;

/** Draws every active giveaway whose end time has passed. Runs on the scheduler. */
class DrawDueCommand extends AbstractCommand
{
    public function __construct(protected DrawService $draws)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('giveaways:draw-due')
            ->setDescription('Close and draw all giveaways past their end time.');
    }

    protected function fire(): int
    {
        $due = Giveaway::query()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', Carbon::now())
            ->get();

        if ($due->isEmpty()) {
            $this->info('No giveaways are due.');
            return 0;
        }

        foreach ($due as $giveaway) {
            try {
                $this->draws->draw($giveaway);
                $this->info("Drew giveaway #{$giveaway->id}: {$giveaway->title}");
            } catch (\Throwable $e) {
                $this->error("Failed giveaway #{$giveaway->id}: {$e->getMessage()}");
            }
        }

        return 0;
    }
}
