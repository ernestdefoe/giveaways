<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('giveaway_entries', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('giveaway_id');
    $table->unsignedInteger('user_id');
    $table->unsignedInteger('entries')->default(1);  // total weighted entries
    $table->text('sources')->nullable();             // JSON: {base:1, post:2, ...}
    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();

    $table->unique(['giveaway_id', 'user_id']);
    $table->index('giveaway_id');
});
