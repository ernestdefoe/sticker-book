import app from 'flarum/forum/app';
import Modal, { type IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import type Post from 'flarum/common/models/Post';
import { call, rarityName, t } from '../util';
import { loadStyles } from '../styles';
import type { Card, Reaction } from '../types';

export interface ReactionModalAttrs extends IInternalModalAttrs {
  post: Post;
}

/** Pick one of your own stickers to put on a post (or take yours off). */
export default class ReactionModal extends Modal<ReactionModalAttrs> {
  stickers!: Card[] | null;
  filter!: string;
  saving?: boolean;

  oninit(vnode: Mithril.Vnode<ReactionModalAttrs, this>) {
    super.oninit(vnode);
    loadStyles();
    this.stickers = null;
    this.filter = '';
    call<{ stickers: Card[] }>('GET', '/mine')
      .then((data) => (this.stickers = data.stickers))
      .catch(() => (this.stickers = []))
      .finally(() => m.redraw());
  }

  className() {
    return 'StickerReactionModal Modal--small';
  }

  title() {
    return t('react.title');
  }

  content() {
    if (!this.stickers)
      return (
        <div className="Modal-body">
          <LoadingIndicator />
        </div>
      );

    if (!this.stickers.length) {
      return (
        <div className="Modal-body StickerReact-empty">
          <p>{t('react.none')}</p>
          <Link className="Button Button--primary" href={app.route('stickers')} onclick={() => this.hide()}>
            {t('react.go')}
          </Link>
        </div>
      );
    }

    return this.picker(this.stickers);
  }

  /** The member's stickers to pick from, filtered by name once there are many. */
  picker(stickers: Card[]) {
    const mine = (this.attrs.post.attribute<Reaction[] | null>('stickerReactions') || []).find((r) => r.mine);
    const q = this.filter.trim().toLowerCase();
    const shown = q ? stickers.filter((s) => s.name.toLowerCase().includes(q)) : stickers;

    return (
      <div className="Modal-body">
        {stickers.length > 12 ? (
          <input
            className="FormControl StickerReact-filter"
            placeholder={extractText(t('react.filter'))}
            value={this.filter}
            oninput={(e: InputEvent) => (this.filter = (e.target as HTMLInputElement).value)}
          />
        ) : null}
        <div className="StickerReact-grid">
          {shown.map((s) => (
            <button
              className={`StickerReact-pick StickerTile--r${s.rarity} ${mine?.id === s.id ? 'is-mine' : ''}`}
              title={`${s.name} · ${rarityName(s.rarity)}`}
              disabled={this.saving}
              onclick={() => this.react(mine?.id === s.id ? null : s.id)}
            >
              <img src={s.small} alt={s.name} />
            </button>
          ))}
        </div>
        {mine ? (
          <Button className="Button Button--link" onclick={() => this.react(null)}>
            {t('react.remove')}
          </Button>
        ) : null}
      </div>
    );
  }

  react(sticker: number | null) {
    this.saving = true;
    react(this.attrs.post, sticker)
      .then(() => this.hide())
      .catch(() => {
        /* call() already showed the error */
      })
      .finally(() => (this.saving = false));
  }
}

export function react(post: Post, sticker: number | null) {
  return call<{ reactions: Reaction[] }>('POST', '/react', { post: post.id(), sticker }).then((data) => {
    post.pushAttributes({ stickerReactions: data.reactions.length ? data.reactions : null });
    m.redraw();
  });
}
