import app from 'flarum/forum/app';
import Modal, { type IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Avatar from 'flarum/common/components/Avatar';
import username from 'flarum/common/helpers/username';
import type Mithril from 'mithril';
import type User from 'flarum/common/models/User';
import { call, pushUsers, rarityName, t, user } from '../util';
import type { AlbumData, AlbumSticker, SparesData } from '../types';
import { openTrade, type TradeAttrs } from './TradeModal';

export type StickerAttrs = {
  sticker: AlbumSticker;
  album: AlbumData;
  own?: boolean;
};

export type StickerModalAttrs = IInternalModalAttrs & StickerAttrs;

export function openSticker(attrs: StickerAttrs) {
  app.modal.show(StickerModal, attrs);
}

/**
 * One sticker, big. On your own album: offer a spare, or find members who
 * have one to spare. On someone else's: ask them for their spare.
 */
export default class StickerModal extends Modal<StickerModalAttrs> {
  /** [user id, spares] of members with one to spare, once looked up. */
  spares!: [number, number][] | null;

  oninit(vnode: Mithril.Vnode<StickerModalAttrs, this>) {
    super.oninit(vnode);
    this.spares = null;
    // The album's owner as a store user, for the trade form.
    if (this.attrs.album.user) pushUsers([this.attrs.album.user]);
  }

  className() {
    return 'StickerModal Modal--small';
  }

  title() {
    return this.attrs.sticker.name;
  }

  content() {
    const { sticker: s, album, own } = this.attrs;
    const me = app.session.user;
    const canTrade = me && app.forum.attribute('stickerCanTrade');
    const owner = album.user ? user(album.user.id) : null;

    return (
      <div className="Modal-body">
        <div className={`StickerModal-art StickerModal-art--r${s.rarity} ${s.count ? '' : 'is-missing'}`}>
          <img src={s.count ? s.full : s.sil} alt="" />
        </div>
        <p className="StickerModal-meta">
          <span className={`StickerPill StickerPill--r${s.rarity}`}>{rarityName(s.rarity)}</span>
          <span className="StickerModal-have">
            {s.count
              ? own
                ? t('sticker.you_have', { count: s.count, spares: s.count - 1 })
                : t('sticker.they_have', { name: owner?.displayName(), count: s.count })
              : own
                ? t('sticker.you_need')
                : t('sticker.they_need', { name: owner?.displayName() })}
          </span>
        </p>

        {canTrade ? <div className="StickerModal-actions">{this.actions(s, !!own, owner)}</div> : null}
      </div>
    );
  }

  actions(s: AlbumSticker, own: boolean, owner: User | null) {
    if (own && s.count > 1) {
      return (
        <Button className="Button Button--primary Button--block" icon="fas fa-right-left" onclick={() => this.trade({ give: [[s.id, 1]] })}>
          {t('sticker.offer_spare')}
        </Button>
      );
    }
    if (!own && s.count > 1 && owner) {
      return (
        <Button
          className="Button Button--primary Button--block"
          icon="fas fa-hand-holding-heart"
          onclick={() => this.trade({ to: owner, take: [[s.id, 1]] })}
        >
          {t('sticker.ask', { name: owner.displayName() })}
        </Button>
      );
    }
    if (own && !s.count) return this.spareFinder(s);
    return null;
  }

  /** For a sticker the member is missing: find who has one to spare, then propose a trade. */
  spareFinder(s: AlbumSticker) {
    if (this.spares === null) {
      return (
        <Button className="Button Button--block" icon="fas fa-magnifying-glass" loading={this.loading} onclick={() => this.findSpares(s)}>
          {t('sticker.find_spare')}
        </Button>
      );
    }
    if (!this.spares.length) return <p className="StickerModal-none">{t('sticker.no_spares')}</p>;
    return (
      <div className="StickerSpares">
        <h4>{t('sticker.spares_title')}</h4>
        <ul>
          {this.spares.map(([id, n]) => {
            const u = user(id);
            if (!u) return null;
            return (
              <li>
                <span className="StickerSpares-who">
                  <Avatar user={u} /> {username(u)} <span className="StickerSpares-n">{t('sticker.spare_count', { count: n })}</span>
                </span>
                <Button className="Button Button--primary" onclick={() => this.trade({ to: u, take: [[s.id, 1]] })}>
                  {t('sticker.propose')}
                </Button>
              </li>
            );
          })}
        </ul>
      </div>
    );
  }

  findSpares(s: AlbumSticker) {
    this.loading = true;
    call<SparesData>('GET', '/spares', { sticker: s.id })
      .then((data) => {
        pushUsers(data.users);
        this.spares = data.spares;
      })
      .catch(() => {
        /* call() already showed the error */
      })
      .finally(() => {
        this.loading = false;
        m.redraw();
      });
  }

  trade(attrs: TradeAttrs) {
    // Replace this window with the trade form.
    openTrade(attrs);
  }
}
