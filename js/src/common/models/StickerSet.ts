import Model from 'flarum/common/Model';
import type Sticker from './Sticker';

/** A set of stickers (sticker-book-sets, admins only). Hidden while it is a draft. */
export default class StickerSet extends Model {
  name() {
    return Model.attribute<string>('name').call(this);
  }
  description() {
    return Model.attribute<string | null>('description').call(this);
  }
  badgeId() {
    return Model.attribute<number | null>('badgeId').call(this);
  }
  isHidden() {
    return Model.attribute<boolean>('isHidden').call(this);
  }
  position() {
    return Model.attribute<number>('position').call(this);
  }
  /** In order; a sticker deleted since the set was loaded is left out. */
  stickers(): Sticker[] {
    return (Model.hasMany<Sticker>('stickers').call(this) || []).filter((s): s is Sticker => !!s);
  }
}
