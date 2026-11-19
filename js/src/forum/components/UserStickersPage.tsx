import UserPage, { type IUserPageAttrs } from 'flarum/forum/components/UserPage';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import type User from 'flarum/common/models/User';
import useStyles from '../styles';
import { call, t } from '../util';
import type { AlbumData } from '../types';
import Album, { isOwnAlbum } from './Album';

/** Profile → Stickers: a member's album, and their spares to ask for. */
export default class UserStickersPage extends UserPage {
  album!: AlbumData | null;
  onChange!: () => void;
  /** The member whose album is loaded. */
  shownFor!: string | undefined;

  oninit(vnode: Mithril.Vnode<IUserPageAttrs, this>) {
    super.oninit(vnode);
    useStyles();
    this.album = null;
    this.loadUser(m.route.param('username'));
    this.onChange = () => this.user && this.loadAlbum(this.user);
    window.addEventListener('sticker-book:changed', this.onChange);
  }

  onremove(vnode: Mithril.VnodeDOM<IUserPageAttrs, this>) {
    super.onremove(vnode);
    window.removeEventListener('sticker-book:changed', this.onChange);
  }

  show(user: User) {
    super.show(user);
    if (this.shownFor === user.id()) return;
    this.shownFor = user.id();
    this.loadAlbum(user);
  }

  /** The member's album from our endpoint (an empty one if it can't be had). */
  loadAlbum(user: User) {
    call<AlbumData>('GET', '/album', { user: user.slug() }, { quiet: true })
      .then((album) => (this.album = album))
      .catch(() => (this.album = { user: null, sets: [], stats: {} }))
      .finally(() => m.redraw());
  }

  content() {
    if (!this.album) return <LoadingIndicator display="block" />;
    const stats = this.album.stats;
    return (
      <div className="StickerUserPage">
        {stats?.total ? (
          <p className="StickerUserPage-summary">{t('profile.summary', { count: stats.unique, total: stats.total, sets: stats.completed })}</p>
        ) : null}
        <Album album={this.album} own={isOwnAlbum(this.album)} />
      </div>
    );
  }
}
