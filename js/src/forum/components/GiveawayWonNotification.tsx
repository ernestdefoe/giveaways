import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import type Mithril from 'mithril';

/** Renders the "you won a giveaway" alert in the notifications dropdown. */
export default class GiveawayWonNotification extends Notification {
  icon() {
    return 'fas fa-gift';
  }

  href() {
    const data: any = (this.attrs.notification as any).content() || {};
    return data.slug ? app.route('giveaways.show', { slug: data.slug }) : app.route('giveaways.index');
  }

  content(): Mithril.Children {
    return app.translator.trans('ernestdefoe-giveaways.forum.notification.won_content');
  }

  excerpt(): Mithril.Children {
    const data: any = (this.attrs.notification as any).content() || {};
    return data.prize || data.title;
  }
}
