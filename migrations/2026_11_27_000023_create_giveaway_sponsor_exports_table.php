<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/** Every export of a sponsor's opted-in members: who, when, how many rows. No copy of the data. */
return Migration::createTable('giveaway_sponsor_exports', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('sponsor_id');
    $table->unsignedInteger('user_id')->nullable();
    $table->unsignedInteger('rows');
    $table->dateTime('created_at');

    $table->index('sponsor_id');
    $table->foreign('sponsor_id')->references('id')->on('giveaway_sponsors')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
});
