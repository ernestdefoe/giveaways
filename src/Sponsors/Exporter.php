<?php

namespace ErnestDefoe\Giveaways\Sponsors;

use Carbon\Carbon;
use ErnestDefoe\Giveaways\Advent\Calendar;
use ErnestDefoe\Giveaways\Giveaway;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository as Cache;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * The list a sponsor receives: only members who ticked the box for one of
 * its campaigns, haven't withdrawn (withdrawing deletes the row), still have
 * an account and a confirmed email. Username, email, campaign, when they
 * agreed, and the sentence they agreed to; nothing else. Streamed, never
 * stored; every export is logged.
 */
class Exporter
{
    /** Exports per manager per hour. */
    private const PER_HOUR = 10;

    public function __construct(private readonly Cache $cache, private readonly TranslatorInterface $translator)
    {
    }

    /** The CSV, written 500 rows at a time to a temporary stream (spills to disk past 2 MB), never kept. */
    public function csv(Sponsor $sponsor, User $actor): StreamInterface
    {
        // add + increment are atomic: parallel requests can't slip past the limit.
        $key = 'ernestdefoe-giveaways.sponsor-export.'.$actor->id;
        $this->cache->add($key, 0, 3600);
        if ((int) $this->cache->increment($key) > self::PER_HOUR) {
            throw new ValidationException(['export' => $this->translator->trans('ernestdefoe-giveaways.api.sponsors.export_limit')]);
        }

        $out = new Stream('php://temp', 'wb+');
        $out->write(self::line(['username', 'email', 'campaign', 'opted_in_at', 'consent']));
        $count = 0;
        $titles = $this->titles($sponsor);

        OptIn::query()->where('sponsor_id', $sponsor->id)
            ->whereHas('user', fn ($q) => $q->where('is_email_confirmed', true))
            ->with('user')->orderBy('id')
            ->chunkById(500, function ($rows) use ($out, &$count, $titles) {
                $lines = '';
                foreach ($rows as $o) {
                    /** @var OptIn $o */
                    $lines .= self::line([$o->user->username, $o->user->email, $titles[$o->campaign.':'.$o->campaign_id] ?? '', $o->opted_in_at->toIso8601String(), $o->consent_text]);
                    $count++;
                }
                $out->write($lines);
            });

        $log = new Export();
        $log->sponsor_id = $sponsor->id;
        $log->user_id = $actor->id;
        $log->rows = $count;
        $log->created_at = Carbon::now();
        $log->save();
        $out->rewind();

        return $out;
    }

    /**
     * Titles of every campaign this sponsor holds agreements for, even one
     * that has since changed sponsor. One query per kind.
     *
     * @return array<string, string> "giveaway:5" => title
     */
    private function titles(Sponsor $sponsor): array
    {
        $titles = [];
        foreach ([OptIn::GIVEAWAY => Giveaway::class, OptIn::CALENDAR => Calendar::class] as $kind => $model) {
            $ids = OptIn::query()->where('sponsor_id', $sponsor->id)->where('campaign', $kind)->select('campaign_id');
            foreach ($model::query()->whereIn('id', $ids)->get(['id', 'title']) as $c) {
                $titles[$kind.':'.$c->id] = $c->title;
            }
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
        }, $cells))."\r\n";
    }
}
