import { extend } from 'flarum/common/extend';
import Component from 'flarum/common/Component';
import type Mithril from 'mithril';

let list: Mithril.ComponentTypes | null = null;

/**
 * Loads ConsentList (its own chunk, with the sponsors API) the first time the
 * settings page is opened: every other page stays as light as before.
 */
class LazyConsentList extends Component {
  oncreate(vnode: Mithril.VnodeDOM) {
    super.oncreate(vnode);
    if (!list) {
      import('./ConsentList').then((mod) => {
        list = mod.default;
        m.redraw();
      });
    }
  }

  view(): Mithril.Children {
    return list ? m(list) : <span />;
  }
}

/** "Sponsors you share your email with", in the member's settings. */
export default function addToSettings(): void {
  extend('flarum/forum/components/SettingsPage', 'settingsItems', function (items: any) {
    items.add('giveawaySponsors', <LazyConsentList />, -20);
  });
}
