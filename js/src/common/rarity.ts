import app from 'flarum/common/app';
import type { Rarity } from './types';

/** The rarities, by number: 0 common, 1 rare, 2 epic, 3 legendary. */
export const RARITIES = ['common', 'rare', 'epic', 'legendary'];

/** A rarity's name, from the forum's or the admin's strings. */
export const rarityLabel = (area: 'forum' | 'admin', r: Rarity) =>
  app.translator.trans(`ernestdefoe-sticker-book.${area}.rarity.${RARITIES[r] || 'common'}`);
