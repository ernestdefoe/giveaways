<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * A member agreed to share their email address with a sponsor, for one
 * campaign (`campaign` is giveaway or calendar). consent_text is the exact
 * sentence they agreed to. Withdrawing deletes the row; so do deleting the
 * member, the sponsor or the campaign, a GDPR erasure, and the retention purge.
 *
 * The unique key's generated name passes MySQL's 64 characters behind a table
 * prefix, so it is named from the connection's prefix and a short name.
 */
return [
    'up' => function (Builder $schema) {
        $prefix = $schema->getConnection()->getTablePrefix();

        $schema->create('giveaway_sponsor_optins', function (Blueprint $table) use ($prefix) {
            $table->increments('id');
            $table->unsignedInteger('sponsor_id');
            $table->unsignedInteger('user_id');
            $table->string('campaign', 8);
            $table->unsignedInteger('campaign_id');
            $table->text('consent_text');
            $table->dateTime('opted_in_at');

            $table->unique(['sponsor_id', 'user_id', 'campaign', 'campaign_id'], $prefix.'gw_optins_unique');
            $table->index(['campaign', 'campaign_id']);
            $table->foreign('sponsor_id')->references('id')->on('giveaway_sponsors')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('giveaway_sponsor_optins');
    },
];
