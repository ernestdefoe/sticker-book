import type { Card, Rarity } from '../common/types';

export type { Card, Rarity, TradeItem } from '../common/types';

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
