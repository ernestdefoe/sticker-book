import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';
import type User from 'flarum/common/models/User';
import { rarityName, t } from '../util';
import type { AlbumData, AlbumSet, AlbumSticker } from '../types';
import { openSticker } from './StickerModal';

export interface AlbumAttrs extends ComponentAttrs {
  album: AlbumData;
  /** Whose album it is. */
  owner: User;
  own?: boolean;
}

type Filter = 'all' | 'missing' | 'spares';
const FILTERS: Filter[] = ['all', 'missing', 'spares'];

/**
 * An album, set by set: what the member has (art, and ×N for spares) and
 * what they're missing (a silhouette: the server never sent the art).
 * Filters: everything, missing, spares.
 */
export default class Album extends Component<AlbumAttrs> {
  filter: Filter = 'all';

  view() {
    const { album } = this.attrs;
    const keep = (s: AlbumSticker) => this.filter === 'all' || (this.filter === 'missing' ? !s.count : s.count > 1);

    if (!album.sets.length) {
      return (
        <div className="StickerAlbum-empty">
          <Icon name="fas fa-note-sticky" />
          <p>{t('album.no_sets')}</p>
        </div>
      );
    }

    return (
      <div className="StickerAlbum">
        <div className="StickerAlbum-filters" role="tablist">
          {FILTERS.map((f) => (
            <button
              className={`StickerChip ${this.filter === f ? 'is-active' : ''}`}
              role="tab"
              aria-selected={this.filter === f ? 'true' : 'false'}
              onclick={() => (this.filter = f)}
            >
              {t(`album.filter.${f}`)}
            </button>
          ))}
        </div>

        {/* Sets with nothing to show under a filter are left out before the keyed list is drawn: a null among keyed sections breaks Mithril's redraw. */}
        {album.sets
          .map((set) => ({ set, shown: set.stickers.filter(keep) }))
          .filter(({ shown }) => shown.length || this.filter === 'all')
          .map(({ set, shown }) => this.set(set, shown))}
      </div>
    );
  }

  /** One set: its title and progress, and the stickers the filter shows. */
  set(set: AlbumSet, shown: AlbumSticker[]) {
    const { album, owner, own } = this.attrs;
    const have = set.stickers.filter((s) => s.count).length;
    const pct = Math.round((have / set.stickers.length) * 100);

    return (
      <section className={`StickerSet ${set.completedAt ? 'is-complete' : ''}`} key={set.id}>
        <header className="StickerSet-header">
          <div className="StickerSet-title">
            <h3>
              {set.name}
              {set.completedAt ? (
                <span className="StickerSet-done">
                  <Icon name="fas fa-trophy" /> {t('album.complete')}
                </span>
              ) : null}
            </h3>
            {set.description ? <p>{set.description}</p> : null}
          </div>
          <div className="StickerSet-progress" aria-label={t('album.progress_label', { have, total: set.stickers.length })}>
            <span className="StickerSet-count">
              {have}
              <small>/{set.stickers.length}</small>
            </span>
            <span className="StickerSet-bar">
              <span style={{ width: pct + '%' }} />
            </span>
          </div>
        </header>
        <div className="StickerGrid">
          {shown.map((s) => (
            <StickerTile sticker={s} onclick={() => openSticker({ sticker: s, owner, own })} />
          ))}
        </div>
      </section>
    );
  }
}

export interface StickerTileAttrs extends ComponentAttrs {
  sticker: AlbumSticker;
  onclick: () => void;
  compact?: boolean;
}

export class StickerTile extends Component<StickerTileAttrs> {
  view() {
    const { sticker: s, onclick, compact } = this.attrs;
    return (
      <button
        className={`StickerTile StickerTile--r${s.rarity} ${s.count ? '' : 'is-missing'} ${compact ? 'StickerTile--compact' : ''}`}
        onclick={onclick}
        title={`${s.name} · ${rarityName(s.rarity)}`}
      >
        <span className="StickerTile-art">
          <img src={s.count ? s.small : s.sil} alt="" loading="lazy" draggable="false" />
          {s.count > 1 ? <span className="StickerTile-count">×{s.count}</span> : null}
        </span>
        {compact ? null : (
          <span className="StickerTile-label">
            <span className="StickerTile-name">{s.name}</span>
            <span className="StickerTile-rarity">{rarityName(s.rarity)}</span>
          </span>
        )}
      </button>
    );
  }
}

export const isOwnAlbum = (owner: User) => !!app.session.user && owner.id() === app.session.user.id();
