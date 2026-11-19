import app from 'flarum/forum/app';
import Modal, { type IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Avatar from 'flarum/common/components/Avatar';
import Icon from 'flarum/common/components/Icon';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import type User from 'flarum/common/models/User';
import { alertError, call, rarityName, t } from '../util';
import type { AlbumData, AlbumSticker, TradeItem } from '../types';
import type Trade from '../../common/models/Trade';

export type TradeAttrs = {
  /** Who the offer is for; without it, the member picks someone. */
  to?: User;
  give?: TradeItem[];
  take?: TradeItem[];
};

export type TradeModalAttrs = IInternalModalAttrs & TradeAttrs;

export function openTrade(attrs: TradeAttrs = {}) {
  app.modal.show(TradeModal, attrs);
}

/**
 * Offer spares to another member, optionally asking for some of theirs.
 * Only spares are listed (one copy always stays in the album); the server
 * checks all of it again, and again when the offer is accepted.
 */
export default class TradeModal extends Modal<TradeModalAttrs> {
  to!: User | null;
  /** Sticker id → how many. */
  give!: Map<number, number>;
  take!: Map<number, number>;
  message!: string;
  mine!: AlbumData | null;
  theirs!: AlbumData | null;
  query!: string;
  results!: User[];
  timer?: ReturnType<typeof setTimeout>;

  oninit(vnode: Mithril.Vnode<TradeModalAttrs, this>) {
    super.oninit(vnode);
    this.to = this.attrs.to || null;
    this.give = new Map(this.attrs.give || []);
    this.take = new Map(this.attrs.take || []);
    this.message = '';
    this.mine = null;
    this.theirs = null;
    this.query = '';
    this.results = [];

    const me = app.session.user!;
    this.album(me.slug()).then((album) => (this.mine = album));
    if (this.to) this.loadTheirs();
  }

  className() {
    return 'StickerTradeModal';
  }

  title() {
    return t('trade.title');
  }

  album(slug: string | undefined) {
    return call<AlbumData>('GET', '/album', { user: slug }).finally(() => m.redraw());
  }

  loadTheirs() {
    this.theirs = null;
    this.album(this.to!.slug()).then((album) => (this.theirs = album));
  }

  content() {
    const ready = this.to && this.give.size;

    return (
      <div className="Modal-body">
        <div className="StickerTrade-to">
          <label>{t('trade.to')}</label>
          {this.to ? this.person(this.to) : this.picker()}
        </div>

        {this.sides()}

        <div className="Form-group">
          <input
            className="FormControl"
            maxlength="200"
            placeholder={extractText(t('trade.message_placeholder'))}
            value={this.message}
            oninput={(e: InputEvent) => (this.message = (e.target as HTMLInputElement).value)}
          />
        </div>

        <Button
          className="Button Button--primary Button--block"
          icon="fas fa-paper-plane"
          disabled={!ready}
          loading={this.loading}
          onclick={() => this.send()}
        >
          {this.take.size ? t('trade.send') : t('trade.send_gift')}
        </Button>
      </div>
    );
  }

  /** The member the offer is for, with a way to pick someone else (unless the window was opened for them). */
  person(to: User) {
    return (
      <div className="StickerTrade-person">
        <Avatar user={to} />
        <strong>{to.displayName()}</strong>
        {this.attrs.to ? null : (
          <Button className="Button Button--link" onclick={() => ((this.to = null), (this.theirs = null), this.take.clear())}>
            {t('trade.change')}
          </Button>
        )}
      </div>
    );
  }

  /** What the member gives, and what they get (once they've picked whom from). */
  sides() {
    return (
      <div className="StickerTrade-sides">
        {this.side(t('trade.you_give'), this.mine, this.give, this.take, true)}
        <div className="StickerTrade-swap" aria-hidden="true">
          <Icon name="fas fa-right-left" />
        </div>
        {this.to ? (
          this.side(t('trade.you_get', { name: this.to.displayName() }), this.theirs, this.take, this.give, false)
        ) : (
          <div className="StickerTrade-side is-waiting">
            <h4>{t('trade.you_get_anyone')}</h4>
            <p className="StickerTrade-hint">{t('trade.pick_member_first')}</p>
          </div>
        )}
      </div>
    );
  }

  picker() {
    return (
      <div className="StickerTrade-picker">
        <input
          className="FormControl"
          placeholder={extractText(t('trade.search_placeholder'))}
          value={this.query}
          oninput={(e: InputEvent) => this.search((e.target as HTMLInputElement).value)}
          autocomplete="off"
        />
        {this.results.length ? (
          <ul className="StickerTrade-results">
            {this.results.map((u) => (
              <li>
                <button
                  type="button"
                  onclick={() => {
                    this.to = u;
                    this.results = [];
                    this.query = '';
                    this.loadTheirs();
                  }}
                >
                  <Avatar user={u} /> {u.displayName()}
                </button>
              </li>
            ))}
          </ul>
        ) : null}
      </div>
    );
  }

  search(value: string) {
    this.query = value;
    clearTimeout(this.timer);
    if (value.trim().length < 2) {
      this.results = [];
      return;
    }
    this.timer = setTimeout(() => {
      app.store
        .find<User[]>('users', { filter: { q: value.trim() }, page: { limit: 6 } })
        .then((users) => {
          this.results = users.filter((u) => u !== app.session.user);
          m.redraw();
        })
        .catch(() => {
          /* app.request already showed the error */
        });
    }, 250);
  }

  /** One side: that member's spares, tap to add, − / + for how many. */
  side(title: Mithril.Children, album: AlbumData | null, chosen: Map<number, number>, other: Map<number, number>, mine: boolean) {
    if (!album) {
      return (
        <div className="StickerTrade-side">
          <h4>{title}</h4>
          <LoadingIndicator size="small" />
        </div>
      );
    }
    const spares = album.sets.flatMap((set) => set.stickers).filter((s) => s.count > 1 && !other.has(s.id));

    return (
      <div className="StickerTrade-side">
        <h4>{title}</h4>
        {spares.length ? (
          <div className="StickerTrade-grid">{spares.map((s) => this.spare(s, chosen))}</div>
        ) : (
          <p className="StickerTrade-hint">{mine ? t('trade.no_spares_mine') : t('trade.no_spares_theirs')}</p>
        )}
      </div>
    );
  }

  /** One spare: tap to add or take out, − / + for how many (one copy always stays). */
  spare(s: AlbumSticker, chosen: Map<number, number>) {
    const n = chosen.get(s.id) || 0;
    const max = s.count - 1;
    return (
      <div className={`StickerTrade-item StickerTile--r${s.rarity} ${n ? 'is-chosen' : ''}`}>
        <button
          type="button"
          className="StickerTrade-pick"
          title={`${s.name} · ${rarityName(s.rarity)}`}
          aria-pressed={n ? 'true' : 'false'}
          onclick={() => (n ? chosen.delete(s.id) : chosen.set(s.id, 1))}
        >
          <img src={s.small} alt={s.name} draggable="false" />
          <span className="StickerTrade-spare">{n ? `${n}/${max}` : `×${max}`}</span>
        </button>
        {n ? (
          <span className="StickerTrade-stepper">
            <button type="button" aria-label="−" onclick={() => (n > 1 ? chosen.set(s.id, n - 1) : chosen.delete(s.id))}>
              −
            </button>
            <button type="button" aria-label="+" disabled={n >= max} onclick={() => chosen.set(s.id, Math.min(max, n + 1))}>
              +
            </button>
          </span>
        ) : null}
      </div>
    );
  }

  send() {
    const to = this.to!;
    this.loading = true;
    app.store
      .createRecord<Trade>('sticker-book-trades')
      .save(
        {
          give: [...this.give.entries()],
          take: [...this.take.entries()],
          message: this.message || null,
          relationships: { to },
        },
        { errorHandler: alertError }
      )
      .then(() => {
        this.hide();
        app.alerts.show({ type: 'success' }, t('trade.sent', { name: to.displayName() }));
        window.dispatchEvent(new CustomEvent('sticker-book:changed'));
      })
      .catch(() => {
        /* alertError already showed the error */
      })
      .finally(() => {
        this.loading = false;
        m.redraw();
      });
  }
}
