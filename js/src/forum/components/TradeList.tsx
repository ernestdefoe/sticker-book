import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Avatar from 'flarum/common/components/Avatar';
import Icon from 'flarum/common/components/Icon';
import Link from 'flarum/common/components/Link';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import humanTime from 'flarum/common/helpers/humanTime';
import username from 'flarum/common/helpers/username';
import type Mithril from 'mithril';
import type { ApiPayloadSingle } from 'flarum/common/Store';
import { quietError, rarityName, request, t } from '../util';
import type { Card, TradeItem } from '../types';
import type Trade from '../../common/models/Trade';
import { openTrade } from './TradeModal';

type Action = 'accept' | 'decline' | 'cancel';

/** The member's trades: offers to answer, offers they made, and what happened to recent ones. */
export default class TradeList extends Component {
  trades: Trade[] | null = null;
  busy: string | null = null;
  onChange!: () => void;

  oninit(vnode: Mithril.Vnode<ComponentAttrs, this>) {
    super.oninit(vnode);
    this.load();
    this.onChange = () => this.load();
    window.addEventListener('sticker-book:changed', this.onChange);
  }

  onremove() {
    window.removeEventListener('sticker-book:changed', this.onChange);
  }

  load() {
    app.store
      .find<Trade[]>('sticker-book-trades', {}, undefined, { background: true, errorHandler: quietError })
      .then((trades) => (this.trades = trades))
      .catch(() => (this.trades = this.trades || []))
      .finally(() => m.redraw());
  }

  view() {
    if (!this.trades) return <LoadingIndicator display="block" />;
    const me = app.session.user!.id();
    const trades = this.trades;
    const incoming = trades.filter((tr) => tr.status() === 'pending' && tr.to()?.id() === me);
    const outgoing = trades.filter((tr) => tr.status() === 'pending' && tr.from()?.id() === me);
    const past = trades.filter((tr) => tr.status() !== 'pending');

    return (
      <div className="StickerTrades">
        <div className="StickerTrades-top">
          <p>{t('trades.intro')}</p>
          <Button className="Button Button--primary" icon="fas fa-right-left" onclick={() => openTrade()}>
            {t('trades.new')}
          </Button>
        </div>
        {this.section(t('trades.incoming'), incoming, me, t('trades.none_incoming'))}
        {this.section(t('trades.outgoing'), outgoing, me, t('trades.none_outgoing'))}
        {past.length ? this.section(t('trades.past'), past, me) : null}
      </div>
    );
  }

  section(title: Mithril.Children, trades: Trade[], me: string | undefined, empty?: Mithril.Children) {
    return (
      <section className="StickerTrades-section">
        <h3>
          {title} {trades.length ? <span className="StickerTrades-n">{trades.length}</span> : null}
        </h3>
        {trades.length ? <ul>{trades.map((tr) => this.row(tr, me))}</ul> : empty ? <p className="StickerTrades-empty">{empty}</p> : null}
      </section>
    );
  }

  row(tr: Trade, me: string | undefined) {
    const incoming = tr.to()?.id() === me;
    const other = incoming ? tr.from() : tr.to();
    const status = tr.status();
    // What the viewer gives and gets, whichever side they are on.
    const gives = incoming ? tr.take() : tr.give();
    const gets = incoming ? tr.give() : tr.take();

    return (
      <li className={`StickerTradeRow is-${status}`} key={tr.id()}>
        <div className="StickerTradeRow-head">
          <Link href={other ? app.route.user(other) : '#'} className="StickerTradeRow-who">
            <Avatar user={other} /> <strong>{username(other)}</strong>
          </Link>
          <span className="StickerTradeRow-when">{humanTime(tr.createdAt())}</span>
          {status !== 'pending' ? <span className={`StickerTradeRow-status is-${status}`}>{t(`trades.status.${status}`)}</span> : null}
        </div>
        {tr.message() ? <p className="StickerTradeRow-message">“{tr.message()}”</p> : null}
        <div className="StickerTradeRow-items">
          <div>
            <span className="StickerTradeRow-label">{t('trades.you_give')}</span>
            {this.items(gives, tr.stickers())}
          </div>
          <Icon name="fas fa-right-left" className="StickerTradeRow-arrow" />
          <div>
            <span className="StickerTradeRow-label">{t('trades.you_get')}</span>
            {this.items(gets, tr.stickers())}
          </div>
        </div>
        {status === 'pending' ? <div className="StickerTradeRow-actions">{this.actions(tr, incoming)}</div> : null}
      </li>
    );
  }

  /** Accept or decline an offer to the viewer; cancel one they made. */
  actions(tr: Trade, incoming: boolean) {
    const button = (action: Action, className: string, icon?: string) => (
      <Button className={className} icon={icon} loading={this.busy === `${tr.id()}${action}`} onclick={() => this.act(tr, action)}>
        {t(`trades.${action}`)}
      </Button>
    );

    return incoming ? [button('accept', 'Button Button--primary', 'fas fa-check'), button('decline', 'Button')] : [button('cancel', 'Button')];
  }

  items(list: TradeItem[], stickers: Card[]) {
    if (!list.length) return <span className="StickerTradeRow-nothing">{t('trades.nothing')}</span>;
    const byId: Record<number, Card> = Object.fromEntries(stickers.map((s) => [s.id, s]));
    return (
      <span className="StickerTradeRow-stickers">
        {list.map(([id, n]) => {
          const s = byId[id];
          if (!s) return null;
          return (
            <span className={`StickerMini StickerTile--r${s.rarity}`} title={`${s.name} · ${rarityName(s.rarity)}`}>
              <img src={s.small} alt={s.name} />
              {n > 1 ? <span className="StickerMini-n">×{n}</span> : null}
            </span>
          );
        })}
      </span>
    );
  }

  act(tr: Trade, action: Action) {
    this.busy = `${tr.id()}${action}`;
    request<ApiPayloadSingle>('POST', `/sticker-book-trades/${tr.id()}/${action}`)
      .then((payload) => {
        app.store.pushPayload<Trade>(payload);
        if (action === 'accept') app.alerts.show({ type: 'success' }, t('trades.accepted_alert'));
        window.dispatchEvent(new CustomEvent('sticker-book:changed'));
      })
      .catch(() => this.load())
      .finally(() => {
        this.busy = null;
        m.redraw();
      });
  }
}
