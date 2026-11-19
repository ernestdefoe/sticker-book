import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';

/** A row on the collectors board (sticker-book-collectors): a member and how many different stickers they have. */
export default class Collector extends Model {
  uniqueCount() {
    return Model.attribute<number>('uniqueCount').call(this);
  }
  /** How many stickers there are to collect. */
  total() {
    return Model.attribute<number>('total').call(this);
  }
  user() {
    return Model.hasOne<User>('user').call(this) || null;
  }
}
