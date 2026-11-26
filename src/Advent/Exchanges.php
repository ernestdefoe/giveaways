<?php

namespace ErnestDefoe\Giveaways\Advent;

use Flarum\User\User;

/**
 * Secret Santa exchanges an "exchange" door can feature. Nothing, unless
 * Secret Santa is enabled, which binds SecretSantaExchanges.
 */
class Exchanges
{
    /**
     * The exchanges among $ids that $actor may see, keyed by id.
     *
     * @param int[] $ids
     * @return array<int, array{id: int, title: string, signupsOpen: bool, signupEndsAt: string|null}>
     */
    public function visibleTo(array $ids, User $actor): array
    {
        return [];
    }

    /**
     * Exchanges a calendar builder may pick from, newest first.
     *
     * @return list<array{id: int, title: string}>
     */
    public function choices(): array
    {
        return [];
    }

    public function exists(int $id): bool
    {
        return false;
    }
}
