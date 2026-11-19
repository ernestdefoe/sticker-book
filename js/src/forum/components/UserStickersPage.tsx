import UserPage, { type IUserPageAttrs } from 'flarum/forum/components/UserPage';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import type User from 'flarum/common/models/User';
import useStyles from '../styles';
import { call, errorText, t } from '../util';
import type { AlbumData } from '../types';
import Album, { isOwnAlbum } from './Album';

/** Profile → Stickers: a member's album, and their spares to ask for. */
export default class UserStickersPage extends UserPage {
  album!: AlbumData | null;
  /** Why the album couldn't be loaded, shown in its place. */
  error!: string | null;
  onChange!: () => void;
  /** The member whose album is loaded. */
  shownFor!: string | undefined;

  oninit(vnode: Mithril.Vnode<IUserPageAttrs, this>) {
    super.oninit(vnode);
    useStyles();
    this.album = null;
    this.error = null;
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

  /** The member's album from our endpoint (the reason in its place if it can't be had). */
  loadAlbum(user: User) {
    call<AlbumData>('GET', '/album', { user: user.slug() }, { quiet: true })
      .then((album) => ((this.album = album), (this.error = null)))
      .catch((error) => (this.error = errorText(error)))
      .finally(() => m.redraw());
  }

  content() {
    if (this.error) return <p className="StickerTrades-empty">{this.error}</p>;
    if (!this.album || !this.user) return <LoadingIndicator display="block" />;
    const stats = this.album.stats;
    return (
      <div className="StickerUserPage">
        {stats?.total ? (
          <p className="StickerUserPage-summary">{t('profile.summary', { count: stats.unique, total: stats.total, sets: stats.completed })}</p>
        ) : null}
        <Album album={this.album} owner={this.user} own={isOwnAlbum(this.user)} />
      </div>
    );
  }
}
