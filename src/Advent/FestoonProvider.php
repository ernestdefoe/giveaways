<?php

namespace ErnestDefoe\Giveaways\Advent;

use Flarum\Foundation\AbstractServiceProvider;

/** While Festoon is enabled, calendars without a timezone of their own follow Festoon's. */
class FestoonProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->bind(ForumZone::class, FestoonZone::class);
    }
}
