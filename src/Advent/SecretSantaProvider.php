<?php

namespace ErnestDefoe\Giveaways\Advent;

use Flarum\Foundation\AbstractServiceProvider;

/** While Secret Santa is enabled, a door can feature one of its exchanges. */
class SecretSantaProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->bind(Exchanges::class, SecretSantaExchanges::class);
    }
}
