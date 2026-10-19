<?php

use Flarum\Database\Migration;

/**
 * A giveaway made from the composer is tied to the post that published it, so
 * its page can point back to the discussion. Both stay null for giveaways made
 * on the giveaways page.
 */
return Migration::addColumns('giveaways', [
    'post_id' => ['integer', 'unsigned' => true, 'nullable' => true],
    'discussion_id' => ['integer', 'unsigned' => true, 'nullable' => true],
]);
