import Model from 'flarum/common/Model';

/**
 * Frontend counterpart to the `giveaways` JSON:API type served by
 * `GiveawayResource`.
 *
 * This model exists primarily so the type is REGISTERED in the store (see
 * `js/src/common/extend.ts`). Giveaway notifications arrive with the giveaway
 * itself as the notification `subject`, sideloaded in `included`. If the type
 * is not registered, `Store.pushObject()` drops that record and throws, then
 * `notification.subject()` resolves to `undefined` and core's
 * `NotificationList` silently skips the row — the unread badge counts up while
 * the dropdown renders nothing.
 *
 * Fields mirror `GiveawayResource::fields()`; `endsAt`/`drawnAt` are exposed
 * over the wire in camelCase even though the columns are `ends_at`/`drawn_at`.
 */
export default class Giveaway extends Model {
  title() {
    return Model.attribute<string>('title').call(this);
  }

  slug() {
    return Model.attribute<string>('slug').call(this);
  }

  prize() {
    return Model.attribute<string>('prize').call(this);
  }

  status() {
    return Model.attribute<string>('status').call(this);
  }

  endsAt() {
    return Model.attribute<Date | null, string | null>('endsAt', Model.transformDate).call(this);
  }

  drawnAt() {
    return Model.attribute<Date | null, string | null>('drawnAt', Model.transformDate).call(this);
  }
}
