<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * A giveaway that belongs to a calendar: a door's prize draw, or the grand
 * prize (calendar_role door | grand). These are unlisted and can't be entered
 * directly; they go with their calendar when it is deleted.
 */
return [
    'up' => function (Builder $schema) {
        $schema->table('giveaways', function (Blueprint $table) {
            $table->unsignedInteger('calendar_id')->nullable();
            $table->string('calendar_role', 8)->nullable();

            $table->foreign('calendar_id')->references('id')->on('giveaway_calendars')->cascadeOnDelete();
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('giveaways', function (Blueprint $table) {
            $table->dropForeign(['calendar_id']);
            $table->dropColumn(['calendar_id', 'calendar_role']);
        });
    },
];
