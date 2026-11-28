<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Sponsors of giveaways and advent calendars. privacy_url is required: it is
 * linked beside the opt-in box. The campaign report's link is stored only as
 * the SHA-256 of its token.
 */
return Migration::createTable('giveaway_sponsors', function (Blueprint $table) {
    $table->increments('id');
    $table->string('name', 120);
    $table->string('slug')->unique();
    $table->string('logo_url', 600)->nullable();
    $table->string('website_url', 600);
    $table->string('privacy_url', 600);
    $table->mediumText('description')->nullable();
    $table->string('report_token_hash', 64)->nullable()->unique();
    $table->dateTime('report_token_created_at')->nullable();
    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();
});
