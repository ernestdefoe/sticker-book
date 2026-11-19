import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';
import type { Card, TradeItem, TradeStatus } from '../types';

/** An offer of spares between two members (sticker-book-trades): only they can see it. */
export default class Trade extends Model {
  status() {
    return Model.attribute<TradeStatus>('status').call(this);
  }
  /** What the sender gives. */
  give() {
    return Model.attribute<TradeItem[]>('give').call(this);
  }
  /** What the sender asks for. */
  take() {
    return Model.attribute<TradeItem[]>('take').call(this);
  }
  message() {
    return Model.attribute<string | null>('message').call(this);
  }
  /** The stickers on both sides, with their art. */
  stickers() {
    return Model.attribute<Card[]>('stickers').call(this);
  }
  createdAt() {
    return Model.attribute<Date, string>('createdAt', Model.transformDate).call(this);
  }
  decidedAt() {
    return Model.attribute<Date | null, string | null>('decidedAt', Model.transformDate).call(this);
  }
  from() {
    return Model.hasOne<User>('from').call(this) || null;
  }
  to() {
    return Model.hasOne<User>('to').call(this) || null;
  }
}
