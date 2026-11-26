<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * An advent calendar: one door a day from starts_on for `days` days, each day
 * starting at midnight in `timezone` (empty = the forum's, see Clock::zone).
 * `settings` holds the eligibility rules copied onto its giveaways, and
 * `reminded_day` the last door the opt-in reminder went out for.
 */
return Migration::createTable('giveaway_calendars', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('user_id')->nullable();
    $table->string('title');
    $table->string('slug')->unique();
    $table->mediumText('description')->nullable();
    $table->date('starts_on');
    $table->unsignedTinyInteger('days')->default(24);
    $table->string('timezone', 64)->default('');
    $table->string('status', 16)->default('draft');
    $table->text('settings')->nullable();
    $table->unsignedTinyInteger('reminded_day')->default(0);
    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();

    $table->index('status');
    $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
});
