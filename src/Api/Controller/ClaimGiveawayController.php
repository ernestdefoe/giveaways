<?php

namespace ErnestDefoe\Giveaways\Api\Controller;

use Carbon\Carbon;
use ErnestDefoe\Giveaways\Api\GiveawayPresenter;
use ErnestDefoe\Giveaways\Giveaway;
use ErnestDefoe\Giveaways\Notification\GiveawayClaimedBlueprint;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Locale\TranslatorInterface;
use Flarum\Notification\NotificationSyncer;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** POST /api/giveaways/{id}/claim — a winner marks their prize as claimed. */
class ClaimGiveawayController implements RequestHandlerInterface
{
    public function __construct(
        protected NotificationSyncer $notifications,
        protected TranslatorInterface $translator
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $id = (int) Arr::get($request->getAttributes(), 'routeParameters.id');
        $g = Giveaway::query()->with(['user', 'category'])->findOrFail($id);

        $win = $g->winners()->where('user_id', $actor->id)->first();
        if (! $win) {
            throw new ValidationException(['claim' => $this->translator->trans('ernestdefoe-giveaways.api.claim_not_winner')]);
        }

        if (! $win->claimed_at) {
            $win->claimed_at = Carbon::now();
            $win->save();

            // Let the host know there's a prize to fulfill (best-effort).
            if ($g->user_id && (int) $g->user_id !== (int) $actor->id) {
                try {
                    $host = User::find($g->user_id);
                    if ($host) {
                        $this->notifications->sync(new GiveawayClaimedBlueprint($g, $actor), [$host]);
                    }
                } catch (\Throwable $e) {
                    // never let a notification failure undo the claim
                }
            }
        }

        return new JsonResponse(['data' => GiveawayPresenter::forActor($actor)->present($g, true)]);
    }
}
