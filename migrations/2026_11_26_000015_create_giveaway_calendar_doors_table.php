<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * A calendar's doors. `kind` is prize (opening enters that day's draw, the
 * giveaway in giveaway_id), reveal (a perk shown only to members who opened
 * it) or exchange (a Secret Santa exchange in exchange_id). `body` is stored
 * as formatter XML, like a post.
 */
return Migration::createTable('giveaway_calendar_doors', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('calendar_id');
    $table->unsignedTinyInteger('day');
    $table->string('kind', 16)->default('reveal');
    $table->string('title')->nullable();
    $table->mediumText('body')->nullable();
    $table->unsignedInteger('giveaway_id')->nullable();
    $table->unsignedInteger('exchange_id')->nullable();

    $table->unique(['calendar_id', 'day']);
    $table->foreign('calendar_id')->references('id')->on('giveaway_calendars')->cascadeOnDelete();
    $table->foreign('giveaway_id')->references('id')->on('giveaways')->nullOnDelete();
});
