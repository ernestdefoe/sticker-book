import Model from 'flarum/common/Model';
import type { Rarity } from '../types';
import type StickerSet from './StickerSet';

/** A sticker in a set (sticker-book-stickers, admins only). Without art (small null) it is in no pack. */
export default class Sticker extends Model {
  name() {
    return Model.attribute<string>('name').call(this);
  }
  rarity() {
    return Model.attribute<Rarity>('rarity').call(this);
  }
  position() {
    return Model.attribute<number>('position').call(this);
  }
  full() {
    return Model.attribute<string | null>('full').call(this);
  }
  small() {
    return Model.attribute<string | null>('small').call(this);
  }
  /** Members holding at least one. */
  ownerCount() {
    return Model.attribute<number>('ownerCount').call(this);
  }
  set() {
    return Model.hasOne<StickerSet>('set').call(this) || null;
  }
}
