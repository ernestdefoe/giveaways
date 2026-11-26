<?php

namespace ErnestDefoe\Giveaways\Advent;

use ErnestDefoe\Festoon\Config;

/** Festoon's timezone (itself falling back to Logo Manager's). Bound only while Festoon is enabled. */
class FestoonZone extends ForumZone
{
    public function __construct(private readonly Config $festoon)
    {
    }

    public function name(): string
    {
        return $this->festoon->timezone();
    }
}
