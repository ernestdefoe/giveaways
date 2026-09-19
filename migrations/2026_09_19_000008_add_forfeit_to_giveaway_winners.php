<?php

use Flarum\Database\Migration;

/**
 * A winner who cannot answer the skill-testing question forfeits, and the prize
 * is redrawn. Both facts are recorded per winner row: how many wrong answers
 * they have given, and when (if ever) they forfeited. Forfeited rows are kept,
 * never deleted — they are part of the public record of how the prize moved,
 * and a verifier needs them to reproduce the replacement pick.
 */
return Migration::addColumns('giveaway_winners', [
    'skill_attempts' => ['integer', 'unsigned' => true, 'default' => 0],
    'forfeited_at'   => ['dateTime', 'nullable' => true],
]);
