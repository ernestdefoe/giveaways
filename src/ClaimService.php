<?php

namespace ErnestDefoe\Giveaways;

use Carbon\Carbon;
use ErnestDefoe\Giveaways\Notification\GiveawayClaimedBlueprint;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\Notification\NotificationSyncer;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository as Cache;
use Psr\Log\LoggerInterface;

/** A winner claims their prize, answering the skill-testing question if there is one. */
class ClaimService
{
    public function __construct(
        protected NotificationSyncer $notifications,
        protected TranslatorInterface $translator,
        protected DrawService $draws,
        protected Cache $cache,
        protected LoggerInterface $log,
    ) {
    }

    public function claim(Giveaway $g, User $actor, string $answer): void
    {
        $actor->assertRegistered();

        $win = $g->winners()->where('user_id', $actor->id)->first();
        if (! $win) {
            throw new ValidationException(['claim' => $this->translator->trans('ernestdefoe-giveaways.api.claim_not_winner')]);
        }

        $answer = trim($answer);

        // The row lock below serialises claims on MySQL and PostgreSQL; SQLite
        // ignores it, so a cache lock per giveaway guards it too. Never waited
        // on: a second claim at the same moment is told to try again.
        $lock = $this->cache->lock('ernestdefoe-giveaways.claim.'.$g->id, 10);
        if (! $lock->get()) {
            throw new ValidationException(['answer' => $this->translator->trans('ernestdefoe-giveaways.api.busy')]);
        }

        try {
            $this->decide($g, $win, $actor, $answer);
        } finally {
            $lock->release();
        }
    }

    private function decide(Giveaway $g, GiveawayWinner $win, User $actor, string $answer): void
    {
        // The winner row is locked for the whole decision. Without it, a winner
        // firing many claims at once read the same attempt count in each and got
        // far more guesses than the limit, and several "last" wrong answers each
        // forfeited and promoted a replacement.
        [$error, $params, $justClaimed] = $g->getConnection()->transaction(fn () => $this->judge($g, $win, $answer));

        // Translated after the transaction, so a message can never roll back a
        // counted attempt.
        if ($error !== null) {
            throw new ValidationException(['answer' => $this->translator->trans('ernestdefoe-giveaways.api.'.$error, $params)]);
        }

        if ($justClaimed) {
            $this->tellHost($g, $actor);
        }
    }

    /**
     * Inside the transaction: the skill question, then the claim.
     *
     * @return array{0: string|null, 1: array<string, int>, 2: bool} error key, its parameters, whether it was claimed now
     */
    private function judge(Giveaway $g, GiveawayWinner $win, string $answer): array
    {
        $win = GiveawayWinner::query()->whereKey($win->id)->lockForUpdate()->first();

        // The skill-testing question is put to the drawn winner, not to every
        // entrant: the prize is only awarded once this person answers it
        // correctly, which is what makes the award a contest of mixed skill.
        // An already-claimed prize is never re-gated.
        if (! $win->claimed_at && $g->requiresSkillAnswer()) {
            if ($win->isForfeited()) {
                return ['skill_already_forfeited', [], false];
            }

            // No answer is not a wrong answer: it never burns an attempt.
            if ($answer === '') {
                return ['skill_answer_missing', [], false];
            }

            if (! $g->skillAnswerMatches($answer)) {
                $win->skill_attempts = (int) $win->skill_attempts + 1;
                $limit = $g->skillAttemptLimit();

                if ($limit > 0 && $win->skill_attempts >= $limit) {
                    // Forfeit, then promote the next entrant the published seed
                    // would have revealed. The row stays as part of the record.
                    $win->forfeited_at = Carbon::now();
                    $win->save();
                    $this->draws->drawReplacement($g, $win);

                    return ['skill_forfeited', [], false];
                }

                $win->save();

                return $limit > 0
                    ? ['skill_answer_wrong_attempts', ['count' => $limit - $win->skill_attempts], false]
                    : ['skill_answer_wrong', [], false];
            }
        }

        if ($win->claimed_at || $win->isForfeited()) {
            return [null, [], false];
        }

        $win->claimed_at = Carbon::now();
        $win->save();

        return [null, [], true];
    }

    /** Let the host know there's a prize to fulfil (best-effort). */
    private function tellHost(Giveaway $g, User $actor): void
    {
        if (! $g->user_id || (int) $g->user_id === (int) $actor->id) {
            return;
        }

        try {
            $host = User::find($g->user_id);
            if ($host) {
                $this->notifications->sync(new GiveawayClaimedBlueprint($g, $actor), [$host]);
            }
        } catch (\Throwable $e) {
            // Never let a notification failure undo the claim.
            $this->log->warning('[giveaways] claim notification for giveaway '.$g->id.' failed: '.$e->getMessage());
        }
    }
}
