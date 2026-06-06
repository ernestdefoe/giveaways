<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('giveaway_categories', function (Blueprint $table) {
    $table->increments('id');
    $table->string('name');
    $table->string('slug')->unique();
    $table->string('color', 20)->default('#69c6b9');
    $table->string('icon', 60)->nullable();
    $table->integer('position')->default(0);
    $table->timestamp('created_at')->nullable();
    $table->timestamp('updated_at')->nullable();
});
