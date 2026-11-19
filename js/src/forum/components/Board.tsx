import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import Avatar from 'flarum/common/components/Avatar';
import Link from 'flarum/common/components/Link';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import username from 'flarum/common/helpers/username';
import { errorText, quietError, t } from '../util';
import type Collector from '../../common/models/Collector';

const MEDALS = ['🥇', '🥈', '🥉'];

/** The members with the most different stickers. */
export default class Board extends Component {
  collectors: Collector[] | null = null;
  /** Why the board couldn't be loaded, shown in its place. */
  error: string | null = null;

  oninit(vnode: Mithril.Vnode<ComponentAttrs, this>) {
    super.oninit(vnode);
    app.store
      .find<Collector[]>('sticker-book-collectors', {}, undefined, { background: true, errorHandler: quietError })
      .then((collectors) => (this.collectors = collectors))
      .catch((error) => (this.error = errorText(error)))
      .finally(() => m.redraw());
  }

  view() {
    if (this.error) return <p className="StickerTrades-empty">{this.error}</p>;
    if (!this.collectors) return <LoadingIndicator display="block" />;
    if (!this.collectors.length) return <p className="StickerTrades-empty">{t('board.empty')}</p>;

    return (
      <ol className="StickerBoard">
        {this.collectors.map((row, i) => {
          const u = row.user();
          const unique = row.uniqueCount();
          const total = row.total();
          return (
            <li className="StickerBoard-row">
              <span className="StickerBoard-rank">{MEDALS[i] || i + 1}</span>
              <Link href={u ? app.route('user.stickers', { username: u.slug() }) : '#'} className="StickerBoard-who">
                <Avatar user={u} /> <span>{username(u)}</span>
              </Link>
              <span className="StickerBoard-bar">
                <span style={{ width: (total ? Math.round((unique / total) * 100) : 0) + '%' }} />
              </span>
              <span className="StickerBoard-n">{t('board.count', { count: unique, total })}</span>
            </li>
          );
        })}
      </ol>
    );
  }
}
