import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';

/** One member's spares of one sticker (sticker-book-spares, id "user-sticker"), to trade with. */
export default class Spare extends Model {
  /** Copies they can trade (one always stays in their album). */
  spares() {
    return Model.attribute<number>('spares').call(this);
  }
  user() {
    return Model.hasOne<User>('user').call(this) || null;
  }
}
