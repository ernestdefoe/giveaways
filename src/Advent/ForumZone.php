<?php

namespace ErnestDefoe\Giveaways\Advent;

/**
 * The timezone a calendar uses when it doesn't name its own. UTC unless
 * Festoon is enabled, which binds FestoonZone so the doors open at the same
 * midnight as Festoon's decorations change.
 */
class ForumZone
{
    public function name(): string
    {
        return 'UTC';
    }
}
