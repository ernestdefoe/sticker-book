import app from 'flarum/forum/app';
import { rarityLabel } from '../common/rarity';
import type { PackStatus, Rarity } from './types';

export { alertError, call, errorText, quietError, request } from '../common/api';

export const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`ernestdefoe-sticker-book.forum.${key}`, params);

export const rarityName = (r: Rarity) => rarityLabel('forum', r);

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
