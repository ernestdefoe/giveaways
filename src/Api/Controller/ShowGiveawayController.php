<?php

namespace ErnestDefoe\Giveaways\Api\Controller;

use ErnestDefoe\Giveaways\Api\GiveawayPresenter;
use ErnestDefoe\Giveaways\Giveaway;
use Flarum\Http\RequestUtil;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
        $key = rawurldecode((string) Arr::get($request->getAttributes(), 'routeParameters.id'));

        // The slug first: a title of only digits used to get a slug like
        // "2024", which was then looked up as id 2024 and 404ed. The id is the
        // fallback, for the routes and notifications that pass one.
        $g = Giveaway::query()->with(['user', 'category'])->where('slug', $key)->first()
            ?? (ctype_digit($key) ? Giveaway::query()->with(['user', 'category'])->find((int) $key) : null);

        if (! $g) {
            throw new ModelNotFoundException();
        }

        return new JsonResponse(['data' => GiveawayPresenter::forActor($actor)->present($g, true)]);
    }
}
