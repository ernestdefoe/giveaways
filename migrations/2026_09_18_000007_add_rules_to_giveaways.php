<?php

use Flarum\Database\Migration;

/**
 * Official rules get their own column rather than sharing `description`.
 *
 * Contest law in many places requires the full rules — eligibility, how and
 * when winners are picked, the deadline, odds, sponsor — to be published up
 * front, and they run far longer than the blurb above the Enter button. A
 * mediumText column keeps them clear of TEXT's 65,535-*byte* ceiling, which
 * 20,000 multi-byte characters can exceed.
 *
 * The skill-testing question lives in the existing `settings` JSON alongside
 * the other entry conditions (min_posts, min_age_days), so it needs no column.
 */
return Migration::addColumns('giveaways', [
    'rules' => ['mediumText', 'nullable' => true],
]);
