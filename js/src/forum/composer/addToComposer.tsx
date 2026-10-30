import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Button from 'flarum/common/components/Button';
import classList from 'flarum/common/utils/classList';

/**
 * "Add giveaway" in the discussion and reply composers. The giveaway is saved
 * as a draft only its maker can see, and its link goes into the post; the
 * server publishes it when the post is posted (see PublishFromPost). All but
 * the button lives in the form's lazy chunk. In a reply it follows the
 * discussion title, which must stay first: phones lift the first header item
 * into the top bar.
 */
export default function addToComposer(): void {
  ['flarum/forum/components/DiscussionComposer', 'flarum/forum/components/ReplyComposer'].forEach((path) =>
    extend(path, 'headerItems', function (this: any, items: any) {
      if (!app.forum.attribute('canCreateGiveaways')) return;

      const reply = path.endsWith('ReplyComposer');

      items.add(
        'giveaway',
        <Button
          className={classList('Button Button--ua-reset ComposerBody-giveaway', reply && 'ComposerBody-giveaway--reply')}
          icon="fas fa-gift"
          onclick={() => import('../components/GiveawayFormModal').then(({ openFromComposer }) => openFromComposer(this.composer))}
        >
          {app.translator.trans(`ernestdefoe-giveaways.forum.composer.${this.composer.fields.giveaway ? 'edit' : 'add'}`)}
        </Button>,
        reply ? -10 : 1
      );
    })
  );
}
