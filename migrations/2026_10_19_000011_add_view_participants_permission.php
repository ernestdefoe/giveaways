<?php

use Flarum\Database\Migration;
use Flarum\Group\Group;

// Who entered, and when, is visible to moderators by default. A giveaway's
// host always sees their own list; managers see every list.
return Migration::addPermissions([
    'giveaways.viewParticipants' => Group::MODERATOR_ID,
]);
