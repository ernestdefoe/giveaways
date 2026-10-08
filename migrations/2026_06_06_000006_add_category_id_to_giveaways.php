<?php

use Flarum\Database\Migration;

return Migration::addColumns('giveaways', [
    'category_id' => ['integer', 'unsigned' => true, 'nullable' => true],
]);
