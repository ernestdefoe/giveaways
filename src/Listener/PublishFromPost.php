<?php

namespace ErnestDefoe\Giveaways\Listener;

use ErnestDefoe\Giveaways\Giveaway;
use Flarum\Post\CommentPost;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Revised;
use Psr\Log\LoggerInterface;

/**
 * A giveaway made from the composer is saved as a draft, seen only by its
 * maker, and its link is put in the post. When that post goes up, the draft
 * goes public with it.
 *
 * The link in the post is the tie, not a composer field, so this also works for
 * a post saved with fof/drafts and posted later, by hand or on its schedule: the
 * draft keeps the link in its content, and posting it fires Posted like any
 * other post. A post held for approval publishes nothing until it is approved.
 *
 * Only the post author's own drafts are published, and only one that still
 * ends in the future; an expired one stays a draft for its maker to fix.
 */
class PublishFromPost
{
    public function __construct(protected LoggerInterface $log)
    {
    }

    /** @param Posted|Revised|object $event */
    public function handle(object $event): void
    {
        $post = $event->post ?? null;
        if (! $post instanceof CommentPost || ! $post->user_id) {
            return;
        }

        // An edit only publishes when the author makes it.
        if ($event instanceof Revised && (int) $event->actor->id !== (int) $post->user_id) {
            return;
        }

        // Not public yet: awaiting approval, or hidden.
        if ($post->getAttribute('is_approved') === false || $post->getAttribute('is_approved') === 0 || $post->hidden_at) {
            return;
        }

        $xml = (string) $post->parsed_content;
        if (! str_contains($xml, '/giveaways/')
            || ! preg_match_all('#/giveaways/([^\s/?\#"\'<>()\[\]]+)#u', $xml, $m)) {
            return;
        }

        $slugs = array_slice(array_values(array_unique(array_map('rawurldecode', $m[1]))), 0, 20);

        try {
            $drafts = Giveaway::query()
                ->where('status', 'draft')
                ->where('user_id', $post->user_id)
                ->whereIn('slug', $slugs)
                ->get();

            foreach ($drafts as $g) {
                if ($g->ends_at && $g->ends_at->isPast()) {
                    continue;
                }
                $g->status = 'active';
                $g->post_id = $post->id;
                $g->discussion_id = $post->discussion_id;
                $g->save();
            }
        } catch (\Throwable $e) {
            // Publishing a giveaway must never stop the post itself.
            $this->log->warning('[giveaways] publishing from post '.$post->id.' failed: '.$e->getMessage());
        }
    }
}
