<?php

namespace ErnestDefoe\Giveaways\Sponsors;

use Carbon\Carbon;
use ErnestDefoe\Giveaways\Advent\Calendar;
use ErnestDefoe\Giveaways\Giveaway;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Sharing a member's email address with a campaign's sponsor. Recorded only
 * when the member ticks the box (never by default, never as a condition of
 * entering), with the exact sentence they agreed to, which names who gets
 * the data, what and why, and links the sponsor's privacy policy. Unticking
 * deletes it at once.
 */
class Consent
{
    public function __construct(
        private readonly Campaigns $campaigns,
        private readonly TranslatorInterface $translator,
        private readonly SettingsRepositoryInterface $settings,
    ) {
    }

    /** What the member agrees to, word for word as the box shows it. */
    public function sentence(Sponsor $sponsor, Giveaway|Calendar $campaign): string
    {
        return $this->translator->trans('ernestdefoe-giveaways.api.sponsors.consent', [
            'forum' => (string) $this->settings->get('forum_title'),
            'sponsor' => $sponsor->name,
            'campaign' => $campaign->title,
            'privacy' => $sponsor->privacy_url,
        ]);
    }

    /** Tick (true) or untick (false) the box for one campaign. */
    public function set(User $actor, string $campaign, int $id, bool $on): void
    {
        $actor->assertRegistered();

        $c = $this->campaigns->find($campaign, $id, $actor) ?? throw new ModelNotFoundException();
        $sponsor = $c->sponsor;
        if (! $sponsor) {
            throw new ValidationException(['sponsor' => $this->translator->trans('ernestdefoe-giveaways.api.sponsors.not_sponsored')]);
        }

        $mine = OptIn::query()->where('sponsor_id', $sponsor->id)->where('user_id', $actor->id)
            ->where('campaign', Campaigns::kind($c))->where('campaign_id', $c->id);

        if (! $on) {
            $mine->delete();

            return;
        }
        // Only a confirmed address is shared: the sponsor would otherwise get one nobody has checked.
        if (! $actor->is_email_confirmed) {
            throw new ValidationException(['sponsor' => $this->translator->trans('ernestdefoe-giveaways.api.sponsors.confirm_email')]);
        }
        if ($mine->exists()) {
            return;
        }

        $optIn = new OptIn();
        $optIn->sponsor_id = $sponsor->id;
        $optIn->user_id = $actor->id;
        $optIn->campaign = Campaigns::kind($c);
        $optIn->campaign_id = $c->id;
        $optIn->consent_text = $this->sentence($sponsor, $c);
        $optIn->opted_in_at = Carbon::now();
        $optIn->save();
    }

    /**
     * Which of these campaigns the member is sharing with their sponsor.
     *
     * @param int[] $ids
     * @return array<int, bool>
     */
    public function mine(?User $actor, string $campaign, array $ids): array
    {
        if (! $actor || $actor->isGuest() || ! $ids) {
            return [];
        }

        return OptIn::query()->where('user_id', $actor->id)->where('campaign', $campaign)->whereIn('campaign_id', $ids)
            ->pluck('campaign_id')->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    /**
     * Everything the member is sharing, for their settings page.
     *
     * @return list<array<string, mixed>>
     */
    public function listFor(User $actor): array
    {
        return OptIn::query()->where('user_id', $actor->id)->with('sponsor')->orderByDesc('opted_in_at')->limit(100)->get()
            ->map(fn (OptIn $o) => [
                'sponsor' => $o->sponsor?->name,
                'privacyUrl' => $o->sponsor?->privacy_url,
                'campaign' => $o->campaign,
                'campaignId' => $o->campaign_id,
                'consent' => $o->consent_text,
                'optedInAt' => $o->opted_in_at->toIso8601String(),
            ])->all();
    }
}
