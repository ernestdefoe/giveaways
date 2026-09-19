<?php

namespace ErnestDefoe\Giveaways\Api\Controller;

use Carbon\Carbon;
use ErnestDefoe\Giveaways\Api\GiveawayPresenter;
use ErnestDefoe\Giveaways\DrawService;
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
        protected TranslatorInterface $translator,
        protected DrawService $draws
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

        // The skill-testing question is put to the drawn winner, not to every
        // entrant: the prize is only awarded once this person answers it
        // correctly, which is what makes the award a contest of mixed skill.
        // An already-claimed prize is never re-gated.
        if (! $win->claimed_at && $g->requiresSkillAnswer()) {
            if ($win->isForfeited()) {
                throw new ValidationException(['answer' => $this->translator->trans('ernestdefoe-giveaways.api.skill_already_forfeited')]);
            }

            $answer = Arr::get((array) $request->getParsedBody(), 'data.attributes.answer');
            $answer = is_string($answer) ? trim($answer) : '';

            // No answer is not a wrong answer: it never burns an attempt.
            if ($answer === '') {
                throw new ValidationException(['answer' => $this->translator->trans('ernestdefoe-giveaways.api.skill_answer_missing')]);
            }

            if (! $g->skillAnswerMatches($answer)) {
                $win->skill_attempts = (int) $win->skill_attempts + 1;
                $limit = $g->skillAttemptLimit();
                $outOfAttempts = $limit > 0 && $win->skill_attempts >= $limit;

                if ($outOfAttempts) {
                    // Forfeit, then promote the next entrant the published seed
                    // would have revealed. The row stays as part of the record.
                    $win->forfeited_at = Carbon::now();
                    $win->save();
                    $this->draws->drawReplacement($g, $win);

                    throw new ValidationException(['answer' => $this->translator->trans('ernestdefoe-giveaways.api.skill_forfeited')]);
                }

                $win->save();

                throw new ValidationException(['answer' => $limit > 0
                    ? $this->translator->trans('ernestdefoe-giveaways.api.skill_answer_wrong_attempts', ['count' => $limit - $win->skill_attempts])
                    : $this->translator->trans('ernestdefoe-giveaways.api.skill_answer_wrong')]);
            }
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
