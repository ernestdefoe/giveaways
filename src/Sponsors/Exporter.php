<?php

namespace ErnestDefoe\Giveaways\Sponsors;

use Carbon\Carbon;
use ErnestDefoe\Giveaways\Advent\Calendar;
use ErnestDefoe\Giveaways\Giveaway;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * The list a sponsor receives: only members who ticked the box for one of
 * its campaigns, haven't withdrawn (withdrawing deletes the row), still have
 * an account and a confirmed email. Username, email, campaign, when they
 * agreed, and the sentence they agreed to; nothing else. Built in memory,
 * never stored; every export is logged.
 */
class Exporter
{
    /** Exports per manager per hour. */
    private const PER_HOUR = 10;

    public function __construct(private readonly Cache $cache, private readonly TranslatorInterface $translator)
    {
    }

    public function csv(Sponsor $sponsor, User $actor): string
    {
        $key = 'ernestdefoe-giveaways.sponsor-export.'.$actor->id;
        $used = (int) $this->cache->get($key, 0);
        if ($used >= self::PER_HOUR) {
            throw new ValidationException(['export' => $this->translator->trans('ernestdefoe-giveaways.api.sponsors.export_limit')]);
        }
        $this->cache->put($key, $used + 1, 3600);

        $lines = [self::line(['username', 'email', 'campaign', 'opted_in_at', 'consent'])];
        $count = 0;
        $titles = $this->titles($sponsor);

        OptIn::query()->where('sponsor_id', $sponsor->id)
            ->whereHas('user', fn ($q) => $q->where('is_email_confirmed', true))
            ->with('user')->orderBy('id')
            ->chunkById(500, function ($rows) use (&$lines, &$count, $titles) {
                foreach ($rows as $o) {
                    /** @var OptIn $o */
                    $lines[] = self::line([$o->user->username, $o->user->email, $titles[$o->campaign.':'.$o->campaign_id] ?? '', $o->opted_in_at->toIso8601String(), $o->consent_text]);
                    $count++;
                }
            });

        $log = new Export();
        $log->sponsor_id = $sponsor->id;
        $log->user_id = $actor->id;
        $log->rows = $count;
        $log->created_at = Carbon::now();
        $log->save();

        return implode("\r\n", $lines)."\r\n";
    }

    /** @return array<string, string> "giveaway:5" => title */
    private function titles(Sponsor $sponsor): array
    {
        $titles = [];
        foreach (Giveaway::query()->where('sponsor_id', $sponsor->id)->get(['id', 'title']) as $g) {
            $titles['giveaway:'.$g->id] = $g->title;
        }
        foreach (Calendar::query()->where('sponsor_id', $sponsor->id)->get(['id', 'title']) as $c) {
            $titles['calendar:'.$c->id] = $c->title;
        }

        return $titles;
    }

    /**
     * One CSV line. A cell that a spreadsheet would read as a formula (=, +,
     * -, @, tab, carriage return) gets a leading apostrophe.
     *
     * @param list<string> $cells
     */
    private static function line(array $cells): string
    {
        return implode(',', array_map(function (string $cell) {
            if ($cell !== '' && str_contains("=+-@\t\r", $cell[0])) {
                $cell = "'".$cell;
            }

            return '"'.str_replace('"', '""', $cell).'"';
        }, $cells));
    }
}
