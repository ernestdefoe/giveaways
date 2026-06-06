import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import LinkButton from 'flarum/common/components/LinkButton';

import GiveawaysPage from './pages/GiveawaysPage';
import GiveawayPage from './pages/GiveawayPage';
import GiveawayWonNotification from './components/GiveawayWonNotification';
import GiveawayClaimedNotification from './components/GiveawayClaimedNotification';

app.initializers.add('ernestdefoe-giveaways', () => {
  app.routes['giveaways.index'] = { path: '/giveaways', component: GiveawaysPage };
  app.routes['giveaways.show'] = { path: '/giveaways/:slug', component: GiveawayPage };

  app.notificationComponents.giveawayWon = GiveawayWonNotification;
  app.notificationComponents.giveawayClaimed = GiveawayClaimedNotification;

  // Nav link sits with "All Discussions" in the sidebar navigation. Flarum 2
  // exposes these via IndexSidebar.navItems (IndexPage no longer owns the nav).
  extend(IndexSidebar.prototype, 'navItems', function (items: any) {
    if (app.forum.attribute('giveawaysShowNav') === false) return;

    const label =
      app.forum.attribute<string>('giveawaysNavLabel') ||
      app.translator.trans('ernestdefoe-giveaways.forum.nav');

    items.add(
      'giveaways',
      LinkButton.component({ href: app.route('giveaways.index'), icon: 'fas fa-gift' }, label),
      5
    );
  });
});
