<?php

namespace ErnestDefoe\Giveaways\Api\Controller;

use ErnestDefoe\Giveaways\Api\GiveawaySerializer;
use ErnestDefoe\Giveaways\EntryService;
use ErnestDefoe\Giveaways\Giveaway;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** POST /api/giveaways/{id}/enter — register the actor's base entry. */
class EnterGiveawayController implements RequestHandlerInterface
{
    public function __construct(protected EntryService $entries)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $actor->assertCan('giveaways.enter');

        $id = (int) Arr::get($request->getAttributes(), 'routeParameters.id');
        $g = Giveaway::query()->with('user')->findOrFail($id);

        $reason = $this->entries->ineligibleReason($g, $actor);
        if ($reason) {
            throw new ValidationException(['enter' => $reason]);
        }

        $this->entries->enter($g, $actor);

        return new JsonResponse(['data' => GiveawaySerializer::serialize($g, $actor, true)]);
    }
}
