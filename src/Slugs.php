<?php

namespace ErnestDefoe\Giveaways;

use Flarum\Database\AbstractModel;
use Illuminate\Support\Str;

/** Address slugs for giveaways and advent calendars. */
class Slugs
{
    /**
     * A slug is never all digits: /giveaways/2024 would read as giveaway #2024.
     * A title with no Latin letters (Chinese, Japanese, Korean...) keeps its own
     * letters, as core's UTF-8 discussion slugs do, instead of collapsing to a
     * bare number or to nothing.
     */
    public static function for(string $title, string $prefix = 'giveaway'): string
    {
        $slug = Str::slug($title);
        if ($slug === '' || ctype_digit(str_replace('-', '', $slug))) {
            $slug = Str::slug($title, '-', null);
        }
        if ($slug === '' || ctype_digit(str_replace('-', '', $slug))) {
            $slug = trim($prefix.'-'.$slug, '-');
        }

        return mb_substr($slug, 0, 200);
    }

    /**
     * The slug, with -2, -3... added until no other row of $model has it.
     *
     * @param class-string<AbstractModel> $model
     */
    public static function unique(string $model, string $title, ?int $ignoreId = null, string $prefix = 'giveaway'): string
    {
        $base = self::for($title, $prefix);
        $slug = $base;
        $i = 2;
        while ($model::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
