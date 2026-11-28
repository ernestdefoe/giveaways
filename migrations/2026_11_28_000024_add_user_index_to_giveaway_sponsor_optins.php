<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * A member's own agreements (settings list, withdrawing, the box's state,
 * erasure) are looked up by user: an index for that on every driver (MySQL
 * alone indexes the foreign key by itself). Named from the connection's
 * prefix: the generated name passes MySQL's 64 characters behind a long one.
 */
return [
    'up' => function (Builder $schema) {
        $prefix = $schema->getConnection()->getTablePrefix();
        $schema->table('giveaway_sponsor_optins', function (Blueprint $table) use ($prefix) {
            $table->index(['user_id', 'campaign', 'campaign_id'], $prefix.'gw_optins_user');
        });
    },
    'down' => function (Builder $schema) {
        $prefix = $schema->getConnection()->getTablePrefix();
        $schema->table('giveaway_sponsor_optins', function (Blueprint $table) use ($prefix) {
            $table->dropIndex($prefix.'gw_optins_user');
        });
    },
];
