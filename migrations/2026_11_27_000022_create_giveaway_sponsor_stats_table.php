<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * Views and outbound clicks per campaign per day. Counts only: nothing about
 * who. The unique key is named from the connection's prefix (its generated
 * name passes MySQL's 64 characters).
 */
return [
    'up' => function (Builder $schema) {
        $prefix = $schema->getConnection()->getTablePrefix();

        $schema->create('giveaway_sponsor_stats', function (Blueprint $table) use ($prefix) {
            $table->increments('id');
            $table->unsignedInteger('sponsor_id');
            $table->string('campaign', 8);
            $table->unsignedInteger('campaign_id');
            $table->date('day');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('clicks')->default(0);

            $table->unique(['sponsor_id', 'campaign', 'campaign_id', 'day'], $prefix.'gw_stats_unique');
            $table->foreign('sponsor_id')->references('id')->on('giveaway_sponsors')->cascadeOnDelete();
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('giveaway_sponsor_stats');
    },
];
