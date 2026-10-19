import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Button from 'flarum/common/components/Button';

/**
 * "Add giveaway" in the discussion and reply composers. The giveaway is saved
 * as a draft only its maker can see, and its link goes into the post; the
 * server publishes it when the post is posted (see PublishFromPost). All but
 * the button lives in the form's lazy chunk.
 */
export default function addToComposer(): void {
  ['flarum/forum/components/DiscussionComposer', 'flarum/forum/components/ReplyComposer'].forEach((path) =>
    extend(path, 'headerItems', function (this: any, items: any) {
      if (!app.forum.attribute('canCreateGiveaways')) return;

      items.add(
        'giveaway',
        <Button
          className="Button Button--ua-reset ComposerBody-giveaway"
          icon="fas fa-gift"
          onclick={() => import('../components/GiveawayFormModal').then(({ openFromComposer }) => openFromComposer(this.composer))}
        >
          {app.translator.trans(`ernestdefoe-giveaways.forum.composer.${this.composer.fields.giveaway ? 'edit' : 'add'}`)}
        </Button>,
        1
      );
    })
  );
}
