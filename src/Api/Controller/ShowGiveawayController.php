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

/** GET /api/giveaways/{id} — one giveaway with winners + verification data. */
class ShowGiveawayController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $key = (string) Arr::get($request->getAttributes(), 'routeParameters.id');

        $query = Giveaway::query()->with(['user', 'category']);
        $g = ctype_digit($key)
            ? $query->findOrFail((int) $key)
            : $query->where('slug', $key)->firstOrFail();

        return new JsonResponse(['data' => GiveawayPresenter::forActor($actor)->present($g, true)]);
    }
}
