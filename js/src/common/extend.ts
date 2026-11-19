import Extend from 'flarum/common/extenders';
import StickerSet from './models/StickerSet';
import Sticker from './models/Sticker';
import Trade from './models/Trade';

/** Sticker Book's models, shared by the forum and the admin. */
export default [new Extend.Store().add('sticker-book-sets', StickerSet).add('sticker-book-stickers', Sticker).add('sticker-book-trades', Trade)];
