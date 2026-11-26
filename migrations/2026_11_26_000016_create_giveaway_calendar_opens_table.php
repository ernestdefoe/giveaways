<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Who opened which door, and whether a joker opened it late. Every row is one
 * ticket for the calendar's grand prize; the unique key is what makes opening
 * a door twice a no-op.
 */
return Migration::createTable('giveaway_calendar_opens', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('calendar_id');
    $table->unsignedInteger('user_id');
    $table->unsignedTinyInteger('day');
    $table->boolean('via_joker')->default(false);
    $table->dateTime('opened_at');

    $table->unique(['calendar_id', 'user_id', 'day']);
    $table->index(['calendar_id', 'day']);
    $table->foreign('calendar_id')->references('id')->on('giveaway_calendars')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
});
