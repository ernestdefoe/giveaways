import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import type Mithril from 'mithril';

/** "Door N of <calendar> is open." Nothing about what's behind it. */
export default class DoorOpenNotification extends Notification {
  data(): { title?: string; slug?: string; day?: number } {
    return ((this.attrs.notification as any).content() as any) || {};
  }

  icon() {
    return 'fas fa-door-open';
  }

  href() {
    const { slug } = this.data();
    return slug ? app.route('giveaways.advent', { slug }) : app.route('giveaways.index');
  }

  content(): Mithril.Children {
    return app.translator.trans('ernestdefoe-giveaways.forum.advent.notification', { day: this.data().day });
  }

  excerpt(): Mithril.Children {
    return this.data().title;
  }
}
