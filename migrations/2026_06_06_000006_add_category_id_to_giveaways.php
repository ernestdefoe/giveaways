<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::addColumns('giveaways', [
    'category_id' => ['integer', 'unsigned' => true, 'nullable' => true],
]);
