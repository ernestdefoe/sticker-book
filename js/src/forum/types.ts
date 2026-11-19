/** 0 common, 1 rare, 2 epic, 3 legendary. */
export type Rarity = number;

/** A member as our endpoints send them: one compact row each. */
export interface UserRow {
  id: number;
  username: string;
  displayName: string;
  slug: string;
  avatarUrl: string | null;
}

/** A sticker in an album: the art when the member has it, the silhouette when not. */
export interface AlbumSticker {
  id: number;
  name: string;
  rarity: Rarity;
  count: number;
  small?: string;
  full?: string;
  sil?: string;
}

export interface AlbumSet {
  id: number;
  name: string;
  description: string | null;
  completedAt: string | null;
  stickers: AlbumSticker[];
}

export interface AlbumStats {
  unique: number;
  total: number;
  completed: number;
  opened: number;
}

export interface AlbumData {
  /** Null only in the empty album shown when one can't be loaded. */
  user: UserRow | null;
  sets: AlbumSet[];
  stats: Partial<AlbumStats>;
}

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

/** The member's pack status (forum.js has it as the forum's stickerStatus). */
export interface PackStatus {
  packs: number;
  daily: boolean;
  nextDaily: string;
  pityLeft: number | null;
  unique: number;
  opened: number;
  packSize?: number;
}

/** /me: the status, with the odds by rarity and the pack size. */
export interface MeData extends PackStatus {
  odds: Record<string, number>;
  packSize: number;
  pity: number;
}

export interface OpenResult {
  cards: (Card & { new: boolean; count: number })[];
  source: string;
  completed: { id: number; name: string }[];
  status: PackStatus;
}

/** [sticker id, how many] */
export type TradeItem = [number, number];

export type TradeStatus = 'pending' | 'accepted' | 'declined' | 'cancelled' | 'failed' | 'expired';

export interface Trade {
  id: number;
  from: number;
  to: number;
  status: TradeStatus;
  give: TradeItem[];
  take: TradeItem[];
  message: string | null;
  createdAt: string;
  decidedAt: string | null;
}

export interface TradesData {
  trades: Trade[];
  stickers: Card[];
  users: UserRow[];
}

export interface BoardData {
  /** [user id, different stickers] */
  board: [number, number][];
  total: number;
  users?: UserRow[];
}

export interface SparesData {
  /** [user id, spares] */
  spares: [number, number][];
  users: UserRow[];
}

/** A sticker on a post, with how many put it there and whether the viewer did. */
export interface Reaction {
  id: number;
  name: string;
  rarity: Rarity;
  small: string;
  count: number;
  mine: boolean;
}

/** The rarest stickers on a profile's card. */
export interface Showcase {
  top: { name: string; rarity: Rarity; small: string }[];
  unique: number;
  total: number;
}

/** The member a trade is with. */
export interface TradePartner {
  id: number;
  displayName: string | undefined;
  slug: string | undefined;
}
