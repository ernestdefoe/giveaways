<?php

namespace ErnestDefoe\Giveaways\Api;

use ErnestDefoe\Giveaways\Giveaway;
use Flarum\User\User;

/** Plain-array serializer for the giveaway JSON the frontend consumes. */
class GiveawaySerializer
{
    public static function serialize(Giveaway $g, User $actor, bool $full = false): array
    {
        $entrantCount = (int) $g->entries()->count();
        $totalEntries = (int) $g->entries()->sum('entries');
        $myEntry = $actor->isGuest() ? null : $g->entries()->where('user_id', $actor->id)->first();
        $s = $g->settingsArray();

        $canManage = $actor->hasPermission('giveaways.manage')
            || ($g->user_id && (int) $actor->id === (int) $g->user_id && $actor->hasPermission('giveaways.create'));

        $data = [
            'id'           => (int) $g->id,
            'title'        => $g->title,
            'slug'         => $g->slug,
            'prize'        => $g->prize,
            'description'  => $g->description,
            'coverUrl'     => $g->cover_url,
            'winnerCount'  => (int) $g->winner_count,
            'status'       => $g->status,
            'startsAt'     => optional($g->starts_at)->toIso8601String(),
            'endsAt'       => optional($g->ends_at)->toIso8601String(),
            'drawnAt'      => optional($g->drawn_at)->toIso8601String(),
            'running'      => $g->isRunning(),
            'entrantCount' => $entrantCount,
            'totalEntries' => $totalEntries,
            'myEntries'    => $myEntry ? (int) $myEntry->entries : 0,
            'mySources'    => $myEntry ? $myEntry->sourcesArray() : null,
            'postBonus'    => (int) ($s['post_bonus'] ?? 0),
            'minPosts'     => (int) ($s['min_posts'] ?? 0),
            'minAgeDays'   => (int) ($s['min_age_days'] ?? 0),
            'canManage'    => $canManage,
            'createdBy'    => $g->user ? self::user($g->user) : null,
            'category'     => $g->category ? [
                'id'    => (int) $g->category->id,
                'name'  => $g->category->name,
                'slug'  => $g->category->slug,
                'color' => $g->category->color,
                'icon'  => $g->category->icon,
            ] : null,
        ];

        if ($full) {
            $data['winners'] = $g->winners()->orderBy('position')->with('user')->get()
                ->map(fn ($w) => [
                    'position'  => (int) $w->position,
                    'user'      => $w->user ? self::user($w->user) : null,
                    'claimedAt' => optional($w->claimed_at)->toIso8601String(),
                ])->all();
            $data['drawSeed'] = $g->draw_seed;
            $data['entrantHash'] = $g->entrant_hash;
        }

        return $data;
    }

    private static function user(User $u): array
    {
        return [
            'id'          => (int) $u->id,
            'username'    => $u->username,
            'displayName' => $u->display_name,
            'avatarUrl'   => $u->avatar_url,
        ];
    }
}
