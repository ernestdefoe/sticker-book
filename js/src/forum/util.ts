import app from 'flarum/forum/app';
import type User from 'flarum/common/models/User';
import type { PackStatus, Rarity, UserRow } from './types';

export { alertError, call, errorText, quietError, request } from '../common/api';

export const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`ernestdefoe-sticker-book.forum.${key}`, params);

export const RARITIES = ['common', 'rare', 'epic', 'legendary'];
export const rarityName = (r: Rarity) => t(`rarity.${RARITIES[r] || 'common'}`);

/** People arrive as compact rows; the store lets Avatar and username() draw them the usual way. */
export function pushUsers(users: UserRow[] = []) {
  for (const user of users) {
    if (app.store.getById('users', String(user.id))) continue;
    app.store.pushObject({
      type: 'users',
      id: String(user.id),
      attributes: { username: user.username, displayName: user.displayName, slug: user.slug, avatarUrl: user.avatarUrl },
    });
  }
}

export const user = (id: number | null | undefined): User | null => (id ? app.store.getById<User>('users', String(id)) || null : null);

/** The member's pack status, as forum.js knows it (the button in the header). */
export const status = (): PackStatus | undefined => app.forum?.attribute<PackStatus | undefined>('stickerStatus');

export function setStatus(value: Partial<PackStatus> | null | undefined) {
  if (value) app.forum.pushAttributes({ stickerStatus: { ...status(), ...value } });
}

/** "5h 12m", "12m": until a moment. */
export function until(iso: string) {
  const s = Math.max(0, Math.floor((Date.parse(iso) - Date.now()) / 1000));
  const h = Math.floor(s / 3600);
  const mm = Math.floor((s % 3600) / 60);
  return h ? `${h}h ${String(mm).padStart(2, '0')}m` : `${Math.max(1, mm)}m`;
}

export const reducedMotion = () => !!window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

export function logIn() {
  app.modal.show(() => import('flarum/forum/components/LogInModal'));
}
