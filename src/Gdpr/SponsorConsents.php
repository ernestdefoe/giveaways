<?php

namespace ErnestDefoe\Giveaways\Gdpr;

use ErnestDefoe\Giveaways\Sponsors\OptIn;
use Flarum\Gdpr\Data\Type;

/**
 * With flarum/gdpr: a member's export includes what they agreed to share with
 * sponsors, and erasing (or anonymising) their account erases those consents.
 */
class SponsorConsents extends Type
{
    public static function exportDescription(): string
    {
        return self::staticTranslator()->trans('ernestdefoe-giveaways.lib.gdpr.export');
    }

    public static function anonymizeDescription(): string
    {
        return self::deleteDescription();
    }

    public static function deleteDescription(): string
    {
        return self::staticTranslator()->trans('ernestdefoe-giveaways.lib.gdpr.delete');
    }

    public function export(): ?array
    {
        $rows = OptIn::query()->where('user_id', $this->user->id)->with('sponsor')->get()->map(fn (OptIn $o) => [
            'sponsor' => $o->sponsor?->name,
            'campaign' => $o->campaign.' '.$o->campaign_id,
            'consent' => $o->consent_text,
            'opted_in_at' => $o->opted_in_at->toIso8601String(),
        ])->all();

        return $rows ? ['giveaways/sponsor-consents.json' => $this->encodeForExport($rows)] : null;
    }

    public function anonymize(): void
    {
        $this->delete();
    }

    public function delete(): void
    {
        OptIn::query()->where('user_id', $this->user->id)->delete();
    }
}
