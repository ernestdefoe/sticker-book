import Extend from 'flarum/common/extenders';
import Post from 'flarum/common/models/Post';
import User from 'flarum/common/models/User';
import { TradeNotification } from './notifications';
import { loadStyles } from './styles';

// Each page and the shared styles load side by side, so nothing shows unstyled.
const page =
  <T>(load: () => Promise<T>) =>
  () =>
    Promise.all([load(), loadStyles()]).then(([module]) => module);

export default [
  new Extend.Routes()
    .add(
      'stickers',
      '/stickers',
      page(() => import('./components/BookPage'))
    )
    .add(
      'user.stickers',
      '/u/:username/stickers',
      page(() => import('./components/UserStickersPage'))
    ),

  new Extend.Model(Post).attribute('stickerReactions'),
  new Extend.Model(User).attribute('stickerShowcase'),
  new Extend.Notification().add('stickerTrade', TradeNotification),
];
