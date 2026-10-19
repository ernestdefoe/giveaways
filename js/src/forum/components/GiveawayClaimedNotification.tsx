import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import username from 'flarum/common/helpers/username';
import type Mithril from 'mithril';

/** Tells a giveaway host that a winner has claimed their prize. */
export default class GiveawayClaimedNotification extends Notification {
  icon() {
    return 'fas fa-box-open';
  }

  href() {
    const data: any = (this.attrs.notification as any).content() || {};
    return data.slug ? app.route('giveaways.show', { slug: data.slug }) : app.route('giveaways.index');
  }

  content(): Mithril.Children {
    // Core shows one row per giveaway (the latest claim), so say whose it is.
    return app.translator.trans('ernestdefoe-giveaways.forum.notification.claimed_content', {
      username: username((this.attrs.notification as any).fromUser()),
    });
  }

  excerpt(): Mithril.Children {
    const data: any = (this.attrs.notification as any).content() || {};
    return data.prize || data.title;
  }
}
