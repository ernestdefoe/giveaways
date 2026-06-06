<?php

namespace ErnestDefoe\Giveaways\Api\Controller;

use ErnestDefoe\Giveaways\Api\GiveawaySerializer;
use ErnestDefoe\Giveaways\DrawService;
use ErnestDefoe\Giveaways\Giveaway;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** POST /api/giveaways/{id}/draw — manually run the provably-fair draw now. */
class DrawGiveawayController implements RequestHandlerInterface
{
    public function __construct(protected DrawService $draws)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $id = (int) Arr::get($request->getAttributes(), 'routeParameters.id');
        $g = Giveaway::query()->with('user')->findOrFail($id);

        $canManage = $actor->hasPermission('giveaways.manage')
            || ($g->user_id && (int) $actor->id === (int) $g->user_id && $actor->hasPermission('giveaways.create'));
        if (! $canManage) {
            throw new PermissionDeniedException();
        }

        $this->draws->draw($g);
        $g->refresh();
        $g->load('user');

        return new JsonResponse(['data' => GiveawaySerializer::serialize($g, $actor, true)]);
    }
}
