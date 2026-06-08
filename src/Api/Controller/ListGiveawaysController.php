<?php

namespace ErnestDefoe\Giveaways\Api\Controller;

use ErnestDefoe\Giveaways\Api\GiveawayPresenter;
use ErnestDefoe\Giveaways\Giveaway;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** GET /api/giveaways — list (active first, then drawn). */
class ListGiveawaysController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);

        $giveaways = Giveaway::query()->with(['user', 'category'])
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
}
