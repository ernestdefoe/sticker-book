/** 0 common, 1 rare, 2 epic, 3 legendary. */
export type Rarity = number;

/** A sticker from the catalog, as a pack card, a trade item or one of the member's own. */
export interface Card {
  id: number;
  setId: number;
  name: string;
  rarity: Rarity;
  full: string;
  small: string;
  count?: number;
  new?: boolean;
}

/** [sticker id, how many] */
export type TradeItem = [number, number];

export type TradeStatus = 'pending' | 'accepted' | 'declined' | 'cancelled' | 'failed' | 'expired';
