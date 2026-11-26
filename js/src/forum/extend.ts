import Extend from 'flarum/common/extenders';
import common from '../common/extend';
import DoorOpenNotification from './advent/DoorOpenNotification';
import GiveawayWonNotification from './components/GiveawayWonNotification';
import GiveawayClaimedNotification from './components/GiveawayClaimedNotification';

export default [
  ...common,
  // The pages are their own chunks, fetched on first visit.
  new Extend.Routes()
    .add('giveaways.index', '/giveaways', () => import('./pages/GiveawaysPage'))
    .add('giveaways.show', '/giveaways/:slug', () => import('./pages/GiveawayPage'))
    .add('giveaways.advent', '/advent/:slug', () => import('./advent/AdventPage')),
  new Extend.Notification()
    .add('giveawayWon', GiveawayWonNotification)
    .add('giveawayClaimed', GiveawayClaimedNotification)
    .add('giveawayDoorOpen', DoorOpenNotification),
];
