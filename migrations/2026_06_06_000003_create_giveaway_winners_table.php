<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('giveaway_winners', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('giveaway_id');
    $table->unsignedInteger('user_id');
    $table->unsignedSmallInteger('position')->default(1);
    $table->dateTime('claimed_at')->nullable();
    $table->dateTime('created_at')->nullable();

    $table->index('giveaway_id');
    $table->index('user_id');

    // Clean up winner rows automatically when a giveaway or user is removed.
    $table->foreign('giveaway_id')->references('id')->on('giveaways')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
});
