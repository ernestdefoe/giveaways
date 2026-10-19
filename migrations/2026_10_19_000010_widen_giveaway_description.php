<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * The description is now stored as formatted XML, like a post's content, which
 * runs longer than the text typed. TEXT's 65,535-byte ceiling would cut it off,
 * so it gets the same mediumText the rules already have.
 */
return [
    'up' => function (Builder $schema) {
        $schema->table('giveaways', function (Blueprint $table) {
            $table->mediumText('description')->nullable()->change();
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('giveaways', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });
    },
];
