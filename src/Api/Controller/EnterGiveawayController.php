<?php

namespace ErnestDefoe\Giveaways\Api\Controller;

use ErnestDefoe\Giveaways\Api\GiveawayPresenter;
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
        $g = Giveaway::query()->with(['user', 'category'])->findOrFail($id);

        $reason = $this->entries->ineligibleReason($g, $actor);
        if ($reason) {
            throw new ValidationException(['enter' => $reason]);
        }

        // A skill-testing question, where set, must be answered correctly for
        // the entry to exist at all — that is what keeps entry a game of skill
        // rather than a pure lottery.
        $answer = Arr::get((array) $request->getParsedBody(), 'data.attributes.answer');
        $skillError = $this->entries->skillAnswerError($g, is_string($answer) ? $answer : null);
        if ($skillError) {
            throw new ValidationException(['answer' => $skillError]);
        }

        $this->entries->enter($g, $actor);

        return new JsonResponse(['data' => GiveawayPresenter::forActor($actor)->present($g, true)]);
    }
}
