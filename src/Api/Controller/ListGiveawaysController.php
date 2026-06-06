<?php

namespace ErnestDefoe\Giveaways\Api\Controller;

use ErnestDefoe\Giveaways\Api\GiveawaySerializer;
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

        $giveaways = Giveaway::query()->with('user')
            ->orderByRaw("FIELD(status, 'active', 'drawn', 'cancelled')")
            ->orderBy('ends_at', 'desc')
            ->limit(100)->get();

        $data = $giveaways->map(fn (Giveaway $g) => GiveawaySerializer::serialize($g, $actor, false))->all();

        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'canCreate' => $actor->hasPermission('giveaways.create') || $actor->hasPermission('giveaways.manage'),
                'canManage' => $actor->hasPermission('giveaways.manage'),
            ],
        ]);
    }
}
