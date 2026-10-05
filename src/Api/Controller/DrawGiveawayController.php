<?php

namespace ErnestDefoe\Giveaways\Api\Controller;

use ErnestDefoe\Giveaways\Api\GiveawayPresenter;
use ErnestDefoe\Giveaways\DrawService;
use ErnestDefoe\Giveaways\Giveaway;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Locale\TranslatorInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** POST /api/giveaways/{id}/draw — manually run the provably-fair draw now. */
class DrawGiveawayController implements RequestHandlerInterface
{
    public function __construct(protected DrawService $draws, protected TranslatorInterface $translator)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $id = (int) Arr::get($request->getAttributes(), 'routeParameters.id');
        $g = Giveaway::query()->with(['user', 'category'])->findOrFail($id);

        if (! $g->canBeManagedBy($actor)) {
            throw new PermissionDeniedException();
        }
        if ($g->status === 'active' && ! $g->canBeDrawnBy($actor)) {
            throw new ValidationException(['draw' => $this->translator->trans('ernestdefoe-giveaways.api.draw_not_ended')]);
        }

        $this->draws->draw($g);
        $g->refresh();
        $g->load(['user', 'category']);

        return new JsonResponse(['data' => GiveawayPresenter::forActor($actor)->present($g, true)]);
    }
}
