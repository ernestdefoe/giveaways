<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * One winner row per person per giveaway. pick() never repeats a user and a
 * replacement skips everyone already on the board, so this only turns a bug
 * into a database error instead of a silently doubled winner list.
 *
 * Older versions could double-draw before the draw was locked; any duplicate
 * rows that left behind are removed first (the earliest row is kept), or the
 * index could not be created.
 */
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();

        $keep = $db->table('giveaway_winners')
            ->selectRaw('MIN(id) as id')
            ->groupBy('giveaway_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('id');

        foreach ($keep as $id) {
            $row = $db->table('giveaway_winners')->where('id', $id)->first(['giveaway_id', 'user_id']);
            $db->table('giveaway_winners')
                ->where('giveaway_id', $row->giveaway_id)
                ->where('user_id', $row->user_id)
                ->where('id', '>', $id)
                ->delete();
        }

        $schema->table('giveaway_winners', function (Blueprint $table) {
            $table->unique(['giveaway_id', 'user_id']);
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('giveaway_winners', function (Blueprint $table) {
            $table->dropUnique(['giveaway_id', 'user_id']);
        });
    },
];
