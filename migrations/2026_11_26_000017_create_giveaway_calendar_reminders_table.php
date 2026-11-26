<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/** Members who asked for a "today's door is open" alert. Nobody is on it by default. */
return Migration::createTable('giveaway_calendar_reminders', function (Blueprint $table) {
    $table->unsignedInteger('calendar_id');
    $table->unsignedInteger('user_id');

    $table->primary(['calendar_id', 'user_id']);
    $table->foreign('calendar_id')->references('id')->on('giveaway_calendars')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
});
