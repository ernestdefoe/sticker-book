import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Button from 'flarum/common/components/Button';
import LinkButton from 'flarum/common/components/LinkButton';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import type Post from 'flarum/common/models/Post';
import type User from 'flarum/common/models/User';
import type UserPage from 'flarum/forum/components/UserPage';
import type UserCard from 'flarum/forum/components/UserCard';
import type CommentPost from 'flarum/forum/components/CommentPost';
import { rarityName, status, t, logIn } from './util';
import type { Reaction, Showcase } from './types';

export { default as extend } from './extend';

/*
 * forum.js is loaded on every page, so it only wires things up: the pack
 * button, the sidebar link, the profile tab and strip, the trade notification
 * setting and the stickers under posts (the routes are in extend.ts). The
 * pages, the pack reveal and the forms are chunks fetched when first opened.
 */

const openPacks = () => import('./components/Reveal').then((r) => r.openPacks());

app.initializers.add('ernestdefoe-sticker-book', () => {
  addSidebarLink();
  addPackButton();
  mountPackFab();
  addProfileItems();
  addNotificationSetting();
  addPostStickers();
});

function addSidebarLink() {
  extend('flarum/forum/components/IndexSidebar', 'navItems', function (items: ItemList<Mithril.Children>) {
    if (!app.forum.attribute('stickerCanView') || !app.forum.attribute('stickerNav')) return;
    const waiting = status()?.packs || 0;
    items.add(
      'stickers',
      <LinkButton href={app.route('stickers')} icon="fas fa-note-sticky">
        {t('nav')}
        {waiting ? <span className="Button-badge StickerNav-badge">{waiting}</span> : null}
      </LinkButton>,
      78
    );
  });
}

// The pack button: shown while a pack waits, glowing for the free daily one.
function addPackButton() {
  extend('flarum/forum/components/HeaderSecondary', 'items', function (items: ItemList<Mithril.Children>) {
    const s = status();
    if (!s?.packs) return;
    items.add(
      'stickerPacks',
      <Button
        className={`Button Button--flat StickerPackButton ${s.daily ? 'is-daily' : ''}`}
        onclick={openPacks}
        aria-label={t('header.label', { count: s.packs })}
        title={t('header.label', { count: s.packs })}
      >
        <Icon name="fas fa-note-sticky" />
        <span className="StickerPackButton-count">{s.packs}</span>
      </Button>,
      15
    );
  });
}

// On phones the header's buttons live in the drawer, so a waiting pack also floats in a corner.
function mountPackFab() {
  const fab = document.createElement('div');
  fab.className = 'StickerFab-host';
  document.body.appendChild(fab);
  m.mount(fab, {
    view: () => {
      const s = status();
      if (!s?.packs || app.composer?.isVisible?.() || document.body.classList.contains('StickerReveal-open')) return null;
      return (
        <button className={`StickerFab ${s.daily ? 'is-daily' : ''}`} onclick={openPacks} aria-label={t('header.label', { count: s.packs })}>
          <Icon name="fas fa-note-sticky" />
          <span className="StickerPackButton-count">{s.packs}</span>
        </button>
      );
    },
  });
}

function addProfileItems() {
  extend<UserPage, 'navItems'>('flarum/forum/components/UserPage', 'navItems', function (items) {
    if (!this.user || !app.forum.attribute('stickerCanView')) return;
    items.add(
      'stickers',
      <LinkButton href={app.route('user.stickers', { username: this.user.slug() })} icon="fas fa-note-sticky">
        {t('profile.nav')}
      </LinkButton>,
      84
    );
  });

  // The rarest stickers, on the profile's card (only the profile's own request carries them).
  extend<UserCard, 'infoItems'>('flarum/forum/components/UserCard', 'infoItems', function (items) {
    // UserCard's attrs are untyped in core; it always gets a `user`.
    const user = (this.attrs as { user: User }).user;
    const showcase = user.attribute<Showcase | null>('stickerShowcase');
    if (!showcase?.top?.length) return;
    items.add(
      'stickers',
      <Link className="StickerShowcase" href={app.route('user.stickers', { username: user.slug() })}>
        <span className="StickerShowcase-row">
          {showcase.top.map((s) => (
            <span className={`StickerShowcase-item StickerShowcase--r${s.rarity}`} title={`${s.name} · ${rarityName(s.rarity)}`}>
              <img src={s.small} alt={s.name} />
            </span>
          ))}
        </span>
        <span className="StickerShowcase-text">{t('profile.showcase', { count: showcase.unique, total: showcase.total })}</span>
      </Link>,
      -20
    );
  });
}

function addNotificationSetting() {
  extend('flarum/forum/components/NotificationGrid', 'notificationTypes', function (items: ItemList<unknown>) {
    if (!app.forum.attribute('stickerCanTrade')) return;
    items.add('stickerTrade', { name: 'stickerTrade', icon: 'fas fa-right-left', label: t('notification.preference') });
  });
}

// Stickers on posts. The post redraws when its stickers change (its subtree check), not otherwise.
function addPostStickers() {
  extend<CommentPost, 'oninit'>('flarum/forum/components/CommentPost', 'oninit', function () {
    this.subtree.check(() => this.attrs.post.attribute('stickerReactions'));
  });

  extend<CommentPost, 'footerItems'>('flarum/forum/components/CommentPost', 'footerItems', function (items) {
    const post = this.attrs.post;
    const reactions = post.attribute<Reaction[] | null>('stickerReactions');
    if (!reactions?.length || post.isHidden()) return;
    items.add('stickers', <div className="StickerReactions">{reactions.map((r) => reactionButton(post, r))}</div>, 15);
  });

  extend<CommentPost, 'actionItems'>('flarum/forum/components/CommentPost', 'actionItems', function (items) {
    const post = this.attrs.post;
    if (post.isHidden() || !app.forum.attribute('stickerCanReact')) return;
    items.add(
      'sticker',
      <Button
        className="Button Button--link StickerReactButton"
        icon="far fa-note-sticky"
        onclick={() => (app.session.user ? app.modal.show(() => import('./components/ReactionModal'), { post }) : logIn())}
      >
        {t('react.button')}
      </Button>,
      -5
    );
  });
}

/** One sticker under a post: tap to add yours, or take it off again. */
function reactionButton(post: Post, r: Reaction) {
  const canReact = app.session.user && app.forum.attribute('stickerCanReact');
  return (
    <button
      className={`StickerReaction StickerReaction--r${r.rarity} ${r.mine ? 'is-mine' : ''}`}
      title={`${r.name} · ${rarityName(r.rarity)}`}
      aria-pressed={r.mine ? 'true' : 'false'}
      disabled={!canReact}
      onclick={() => import('./components/ReactionModal').then(({ react }) => react(post, r.mine ? null : r.id))}
    >
      <img src={r.small} alt={r.name} loading="lazy" />
      {r.count > 1 ? <span className="StickerReaction-n">{r.count}</span> : null}
    </button>
  );
}
