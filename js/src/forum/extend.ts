import Extend from 'flarum/common/extenders';
import common from '../common/extend';
import DoorOpenNotification from './advent/DoorOpenNotification';

export default [
  ...common,
  // The calendar page is its own chunk, fetched on first visit.
  new Extend.Routes().add('giveaways.advent', '/advent/:slug', () => import('./advent/AdventPage')),
  new Extend.Notification().add('giveawayDoorOpen', DoorOpenNotification),
];
