<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * Deleting a category leaves its giveaways uncategorised, enforced by the
 * database as well as by DeleteCategoryController. Any category id that
 * already points nowhere is cleared first so the key can be added.
 */
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();

        $db->table('giveaways')
            ->whereNotNull('category_id')
            ->whereNotIn('category_id', $db->table('giveaway_categories')->select('id'))
            ->update(['category_id' => null]);

        $schema->table('giveaways', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('giveaway_categories')->nullOnDelete();
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('giveaways', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });
    },
];
