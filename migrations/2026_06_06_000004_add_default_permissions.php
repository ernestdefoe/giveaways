<?php

use Flarum\Database\Migration;

// Members can enter giveaways out of the box; create/manage stay admin-only
// until granted in the Permissions grid.
return Migration::addPermissions([
    'giveaways.enter' => 'member',
]);
