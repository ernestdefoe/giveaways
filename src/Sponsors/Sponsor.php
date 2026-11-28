<?php

namespace ErnestDefoe\Giveaways\Sponsors;

use Flarum\Database\AbstractModel;

/**
 * A sponsor of giveaways and advent calendars.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $logo_url
 * @property string $website_url
 * @property string $privacy_url
 * @property string|null $description
 * @property string|null $report_token_hash
 * @property \Carbon\Carbon|null $report_token_created_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Sponsor extends AbstractModel
{
    protected $table = 'giveaway_sponsors';

    protected $casts = [
        'id' => 'integer',
        'report_token_created_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * An http(s) link, or null. Control characters and spaces are stripped
     * first, so "\x01javascript:" can't slip past the scheme check.
     */
    public static function link(mixed $value): ?string
    {
        $url = preg_replace('/[\x00-\x20\x7f]+/', '', (string) $value) ?? '';
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) ? mb_substr($url, 0, 600) : null;
    }

    /** A path on this forum ("/assets/logo.png"), or null; never "//other.host". */
    public static function localPath(mixed $value): ?string
    {
        $path = preg_replace('/[\x00-\x20\x7f]+/', '', (string) $value) ?? '';

        return str_starts_with($path, '/') && ! str_starts_with($path, '//') && ! str_contains($path, '\\') ? mb_substr($path, 0, 600) : null;
    }
}
