<?php

namespace ErnestDefoe\Giveaways;

use Carbon\Carbon;
use ErnestDefoe\Giveaways\Notification\GiveawayWonBlueprint;
use Flarum\Notification\NotificationSyncer;
use Flarum\User\User;

/**
 * Provably-fair winner selection. At draw time we publish:
 *   - draw_seed     (random, generated now)
 *   - entrant_hash  (sha256 of the canonical "user_id:entries" list, sorted)
 * Anyone holding the entrant list can re-run pick() with the seed and verify
 * the winners — the draw can't be rigged after the fact.
 */
class DrawService
{
    public function __construct(protected NotificationSyncer $notifications)
    {
    }

    public function draw(Giveaway $giveaway): void
    {
        if ($giveaway->status !== 'active') {
            return;
        }

        $entries = $giveaway->entries()->orderBy('user_id')->get(['user_id', 'entries']);

        $canonical = $entries->map(fn ($e) => $e->user_id . ':' . $e->entries)->implode(',');
        $hash = hash('sha256', $canonical);
        $seed = bin2hex(random_bytes(16));

        $pool = $entries->map(fn ($e) => ['user_id' => (int) $e->user_id, 'entries' => max(1, (int) $e->entries)])->values()->all();
        $winnerIds = $this->pick($pool, $seed, (int) $giveaway->winner_count);

        foreach ($winnerIds as $pos => $uid) {
            $w = new GiveawayWinner();
            $w->giveaway_id = $giveaway->id;
            $w->user_id = $uid;
            $w->position = $pos + 1;
            $w->created_at = Carbon::now();
            $w->save();
        }

        $giveaway->status = 'drawn';
        $giveaway->draw_seed = $seed;
        $giveaway->entrant_hash = $hash;
        $giveaway->drawn_at = Carbon::now();
        $giveaway->save();

        $this->notifyWinners($giveaway, $winnerIds);
    }

    /** Send each winner a "you won" alert. Failures here never block the draw. */
    protected function notifyWinners(Giveaway $giveaway, array $winnerIds): void
    {
        foreach ($winnerIds as $pos => $uid) {
            try {
                $user = User::find($uid);
                if ($user) {
                    $this->notifications->sync(
                        new GiveawayWonBlueprint($giveaway, $pos + 1),
                        [$user]
                    );
                }
            } catch (\Throwable $e) {
                // Best-effort: a notification failure must not undo a completed draw.
            }
        }
    }

    /**
     * Deterministic weighted pick of N distinct winners from a [user_id,entries]
     * pool, seeded by $seed. Pure function of (pool, seed) → verifiable.
     *
     * @param array<int, array{user_id:int, entries:int}> $pool
     * @return int[] winner user ids in draw order
     */
    public function pick(array $pool, string $seed, int $count): array
    {
        $winners = [];
        $slots = min($count, count($pool));

        for ($i = 0; $i < $slots; $i++) {
            $total = array_sum(array_column($pool, 'entries'));
            if ($total <= 0) {
                break;
            }
            // 60 bits of the per-slot hash → fits a 64-bit int → uniform-ish mod total.
            $r = hexdec(substr(hash('sha256', $seed . ':' . $i), 0, 15)) % $total;

            $acc = 0;
            $pickIdx = count($pool) - 1;
            foreach ($pool as $idx => $row) {
                $acc += $row['entries'];
                if ($r < $acc) {
                    $pickIdx = $idx;
                    break;
                }
            }
            $winners[] = $pool[$pickIdx]['user_id'];
            array_splice($pool, $pickIdx, 1);
        }

        return $winners;
    }
}
