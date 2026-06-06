<?php

namespace ErnestDefoe\Giveaways\Api\Controller;

use ErnestDefoe\Giveaways\Giveaway;
use ErnestDefoe\Giveaways\GiveawayEntry;
use ErnestDefoe\Giveaways\GiveawayWinner;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** DELETE /api/giveaways/{id} — remove a giveaway and its entries/winners. */
class DeleteGiveawayController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $id = (int) Arr::get($request->getAttributes(), 'routeParameters.id');
        $g = Giveaway::query()->findOrFail($id);

        $canManage = $actor->hasPermission('giveaways.manage')
            || ($g->user_id && (int) $actor->id === (int) $g->user_id && $actor->hasPermission('giveaways.create'));
        if (! $canManage) {
            throw new PermissionDeniedException();
        }

        GiveawayEntry::where('giveaway_id', $g->id)->delete();
        GiveawayWinner::where('giveaway_id', $g->id)->delete();
        $g->delete();

        return new EmptyResponse(204);
    }
}
