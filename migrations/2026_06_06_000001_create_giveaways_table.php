<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('giveaways', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('user_id')->nullable();      // creator
    $table->string('title');
    $table->string('slug')->unique();
    $table->string('prize');
    $table->text('description')->nullable();
    $table->string('cover_url', 600)->nullable();
    $table->unsignedSmallInteger('winner_count')->default(1);
    $table->string('status', 20)->default('active');     // active | drawn | cancelled
    $table->dateTime('starts_at')->nullable();
    $table->dateTime('ends_at');
    $table->text('settings')->nullable();                // JSON: entry methods + eligibility
    $table->string('draw_seed', 64)->nullable();         // provably-fair seed
    $table->string('entrant_hash', 64)->nullable();      // sha256 of entrant list at draw
    $table->dateTime('drawn_at')->nullable();
    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();

    $table->index('status');
    $table->index('ends_at');
});
