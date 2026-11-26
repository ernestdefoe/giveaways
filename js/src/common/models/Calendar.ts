import Model from 'flarum/common/Model';

/**
 * The `giveaway-calendars` type, registered so the "door is open" alert's
 * subject is kept by the store (without it core skips the alert). The page
 * itself reads calendars through common/advent.ts.
 */
export default class Calendar extends Model {
  title = Model.attribute<string>('title');
  slug = Model.attribute<string>('slug');
}
