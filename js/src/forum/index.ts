import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import IndexPage from 'flarum/forum/components/IndexPage';
import LinkButton from 'flarum/common/components/LinkButton';

import GiveawaysPage from './pages/GiveawaysPage';
import GiveawayPage from './pages/GiveawayPage';

app.initializers.add('ernestdefoe-giveaways', () => {
  app.routes['giveaways.index'] = { path: '/giveaways', component: GiveawaysPage };
  app.routes['giveaways.show'] = { path: '/giveaways/:slug', component: GiveawayPage };

  extend(IndexPage.prototype, 'navItems', (items) => {
    if (app.forum.attribute('giveawaysShowNav') === false) return;

    const label =
      app.forum.attribute<string>('giveawaysNavLabel') ||
      app.translator.trans('ernestdefoe-giveaways.forum.nav');

    items.add(
      'giveaways',
      LinkButton.component(
        { href: app.route('giveaways.index'), icon: 'fas fa-gift' },
        label
      ),
      5
    );
  });
});
