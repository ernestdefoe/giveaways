<?php

namespace ErnestDefoe\Giveaways\Sponsors;

use Carbon\Carbon;
use ErnestDefoe\Giveaways\RichText;
use ErnestDefoe\Giveaways\Slugs;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\User\User;

/**
 * Saving a sponsor (managers only). The website and the privacy policy are
 * required http(s) links: the website is where the tracked link sends people,
 * the privacy policy is linked beside the opt-in box.
 */
class SponsorWriter
{
    public function __construct(private readonly RichText $rich, private readonly TranslatorInterface $translator)
    {
    }

    /** @param array<string, mixed> $in */
    public function save(?Sponsor $sponsor, array $in, User $actor): Sponsor
    {
        $sponsor ??= new Sponsor();
        $new = ! $sponsor->exists;
        $errors = [];
        $t = fn (string $key) => $this->translator->trans("ernestdefoe-giveaways.api.sponsors.$key");

        if ($new || array_key_exists('name', $in)) {
            $name = trim((string) ($in['name'] ?? ''));
            $name === '' ? $errors['name'] = $t('name_required') : $sponsor->name = mb_substr($name, 0, 120);
        }
        foreach (['websiteUrl' => 'website_url', 'privacyUrl' => 'privacy_url'] as $from => $column) {
            if ($new || array_key_exists($from, $in)) {
                $url = self::url($in[$from] ?? '');
                $url ? $sponsor->$column = $url : $errors[$from] = $t($column === 'website_url' ? 'website_required' : 'privacy_required');
            }
        }
        if (array_key_exists('logoUrl', $in)) {
            $logo = trim((string) $in['logoUrl']);
            $sponsor->logo_url = $logo === '' ? null : (self::url($logo) ?? (str_starts_with($logo, '/') && ! str_starts_with($logo, '//') ? mb_substr($logo, 0, 600) : null));
        }
        if (array_key_exists('description', $in)) {
            $sponsor->description = $this->rich->store((string) $in['description'], $sponsor, $actor);
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        if (! $sponsor->slug || $sponsor->isDirty('name')) {
            $sponsor->slug = Slugs::unique(Sponsor::class, $sponsor->name, $sponsor->id, 'sponsor');
        }
        $sponsor->created_at ??= Carbon::now();
        $sponsor->updated_at = Carbon::now();
        $sponsor->save();

        return $sponsor;
    }

    /**
     * An http(s) link, or null. Control characters and spaces are stripped
     * first, so "\x01javascript:" can't slip past the scheme check.
     */
    public static function url(mixed $value): ?string
    {
        $url = preg_replace('/[\x00-\x20\x7f]+/', '', (string) $value) ?? '';
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) ? mb_substr($url, 0, 600) : null;
    }
}
