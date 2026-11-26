<?php

namespace ErnestDefoe\Giveaways\Advent;

use ErnestDefoe\SecretSanta\Exchange;
use Flarum\User\User;

/**
 * Secret Santa's exchanges, seen the way Secret Santa shows them: members need
 * its view permission, and only open or drawn exchanges show to anyone but
 * its moderators. Bound only while Secret Santa is enabled.
 */
class SecretSantaExchanges extends Exchanges
{
    private const VIEW = 'secret-santa.view';
    private const MODERATE = 'secret-santa.moderate';

    public function available(): bool
    {
        return true;
    }

    public function visibleTo(array $ids, User $actor): array
    {
        if (! $ids || ! $actor->can(self::VIEW)) {
            return [];
        }

        return Exchange::query()
            ->whereIn('id', $ids)
            ->when(! $actor->can(self::MODERATE), fn ($q) => $q->whereIn('status', [Exchange::OPEN, Exchange::DRAWN]))
            ->get()
            ->mapWithKeys(fn (Exchange $e) => [(int) $e->id => [
                'id' => (int) $e->id,
                'title' => (string) $e->title,
                'signupsOpen' => $e->signupsOpen(),
                'signupEndsAt' => $e->signup_ends_at?->toIso8601String(),
            ]])
            ->all();
    }

    public function choices(): array
    {
        return Exchange::query()
            ->whereIn('status', [Exchange::OPEN, Exchange::DRAWN])
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'title'])
            ->map(fn (Exchange $e) => ['id' => (int) $e->id, 'title' => (string) $e->title])
            ->all();
    }

    public function exists(int $id): bool
    {
        return Exchange::query()->whereKey($id)->exists();
    }
}
