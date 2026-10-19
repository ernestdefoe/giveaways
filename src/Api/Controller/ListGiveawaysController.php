<?php

namespace ErnestDefoe\Giveaways\Api\Controller;

use ErnestDefoe\Giveaways\Api\GiveawayPresenter;
use ErnestDefoe\Giveaways\Giveaway;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/giveaways — list (active first, then drawn).
 *
 * `?filter[slug]=a,b,c` narrows it to the giveaways those links name, which is
 * how giveaway links in posts are turned into cards: one request for every
 * link on the page, through the same query and presenter as the list.
 */
class ListGiveawaysController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);

        $keys = $this->keys($request);

        $giveaways = Giveaway::query()->with(['user', 'category'])
            ->when($keys !== null, function ($q) use ($keys) {
                // A link carries a slug, or an id from before slugs were safe.
                $ids = array_values(array_filter($keys, 'ctype_digit'));
                $q->where(fn ($q) => $q->whereIn('slug', $keys)->when($ids, fn ($q) => $q->orWhereIn('id', $ids)));
            })
            // Portable ordering — FIELD() is MySQL-only (breaks PG/SQLite).
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'drawn' THEN 1 WHEN 'cancelled' THEN 2 ELSE 3 END")
            ->orderBy('ends_at', 'desc')
            ->limit(100)->get();

        // Batch-load per-row aggregates + the actor's own entry/win once (no N+1).
        $presenter = GiveawayPresenter::forList($actor, $giveaways);
        $data = $giveaways->map(fn (Giveaway $g) => $presenter->present($g, false))->all();

        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'canCreate' => $actor->hasPermission('giveaways.create') || $actor->hasPermission('giveaways.manage'),
                'canManage' => $actor->hasPermission('giveaways.manage'),
            ],
        ]);
    }

    /** @return string[]|null the requested slugs, or null for the whole list */
    private function keys(ServerRequestInterface $request): ?array
    {
        $raw = Arr::get($request->getQueryParams(), 'filter.slug');
        if (! is_string($raw)) {
            return null;
        }

        $keys = array_filter(array_map(fn ($k) => mb_substr(trim($k), 0, 255), explode(',', $raw)), 'strlen');

        return array_slice(array_values(array_unique($keys)), 0, 20);
    }
}
