import Extend from 'flarum/common/extenders';

import Giveaway from './models/Giveaway';

export default [
  // Registering the type is what makes giveaway notifications render at all —
  // without it the sideloaded notification subject is discarded by the store
  // and core skips the row. See the comment on the Giveaway model.
  new Extend.Store() //
    .add('giveaways', Giveaway),
];
