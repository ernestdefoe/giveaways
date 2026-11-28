<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/** A giveaway or an advent calendar can be presented by one sponsor. */
return [
    'up' => function (Builder $schema) {
        foreach (['giveaways', 'giveaway_calendars'] as $name) {
            $schema->table($name, function (Blueprint $table) {
                $table->unsignedInteger('sponsor_id')->nullable();
                $table->foreign('sponsor_id')->references('id')->on('giveaway_sponsors')->nullOnDelete();
            });
        }
    },
    'down' => function (Builder $schema) {
        foreach (['giveaways', 'giveaway_calendars'] as $name) {
            $schema->table($name, function (Blueprint $table) {
                $table->dropForeign(['sponsor_id']);
                $table->dropColumn('sponsor_id');
            });
        }
    },
];
