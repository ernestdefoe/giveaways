<?php

use Flarum\Database\Migration;
use Flarum\Group\Group;

// Members can enter giveaways out of the box; create/manage stay admin-only
// until granted in the Permissions grid.
return Migration::addPermissions([
    // A group ID: a group name matches no row, and the grant is skipped.
    'giveaways.enter' => Group::MEMBER_ID,
]);
