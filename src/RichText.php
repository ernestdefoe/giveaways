<?php

namespace ErnestDefoe\Giveaways;

use Flarum\Formatter\Formatter;
use Flarum\User\User;

/**
 * A giveaway's description and rules go through the forum's own formatter, the
 * one posts use: whatever the forum's editor writes (Markdown, BBCode, Scribe's
 * HTML) is parsed on save into s9e XML and rendered to safe HTML on read.
 *
 * Giveaways saved before 0.7.0 hold plain text. Anything that is not formatter
 * XML is parsed on the fly when it is shown, so it still displays, line breaks
 * and all, without a data migration.
 */
class RichText
{
    public function __construct(protected Formatter $formatter)
    {
    }

    /** Parse what was typed into the XML that is stored; null when empty. */
    public function store(string $source, Giveaway $context, User $actor): ?string
    {
        $source = trim(mb_substr($source, 0, 20000));

        return $source === '' ? null : $this->formatter->parse($source, $context, $actor);
    }

    /** The stored value as safe HTML, or null. */
    public function html(?string $stored, Giveaway $context): ?string
    {
        if ($stored === null || trim($stored) === '') {
            return null;
        }

        try {
            $xml = self::isXml($stored) ? $stored : $this->formatter->parse($stored, $context);

            return $this->formatter->render($xml, $context);
        } catch (\Throwable $e) {
            // Never let a formatter hiccup take the page down: show the text.
            return '<p>'.nl2br(e(self::isXml($stored) ? strip_tags($stored) : $stored)).'</p>';
        }
    }

    /** What the editor is filled with when the giveaway is edited. */
    public function source(?string $stored, Giveaway $context): string
    {
        if ($stored === null) {
            return '';
        }

        return self::isXml($stored) ? (string) $this->formatter->unparse($stored, $context) : $stored;
    }

    /** s9e stores plain text as <t>…</t> and rich text as <r>…</r>. */
    public static function isXml(string $value): bool
    {
        return (bool) preg_match('#^<([rt])(?:\s[^>]*)?>.*</\1>$#s', $value);
    }
}
