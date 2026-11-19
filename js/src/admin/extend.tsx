import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';
import SetsEditor from './SetsEditor';

const t = (key: string, params?: Record<string, unknown>) => app.translator.trans(`ernestdefoe-sticker-book.admin.${key}`, params || {});
const setting = (key: string) => `ernestdefoe-sticker-book.${key}`;
const odds = (rarity: string) => () => ({
  setting: setting(`odds_${rarity}`),
  type: 'number',
  min: 0,
  max: 100,
  step: 0.1,
  label: t(`settings.odds_${rarity}`),
});

export default [
  new Extend.Admin()
    .customSetting(() => <SetsEditor />, 300)
    .setting(() => ({ setting: setting('nav'), type: 'boolean', label: t('settings.nav') }), 200)
    .setting(() => ({ setting: setting('reactions'), type: 'boolean', label: t('settings.reactions'), help: t('settings.reactions_help') }), 190)
    .setting(() => ({ setting: setting('pack_size'), type: 'number', min: 1, max: 10, label: t('settings.pack_size') }), 180)
    .customSetting(
      () => (
        <div className="Form-group">
          <label>{t('settings.odds')}</label>
          <p className="helpText">{t('settings.odds_help')}</p>
        </div>
      ),
      171
    )
    .setting(odds('common'), 170)
    .setting(odds('rare'), 169)
    .setting(odds('epic'), 168)
    .setting(odds('legendary'), 167)
    .setting(
      () => ({
        setting: setting('pity'),
        type: 'number',
        min: 0,
        max: 1000,
        label: t('settings.pity'),
        help: t('settings.pity_help'),
      }),
      160
    )
    .setting(
      () => ({ setting: setting('guarantee_rare'), type: 'boolean', label: t('settings.guarantee_rare'), help: t('settings.guarantee_rare_help') }),
      150
    )
    .setting(() => ({ setting: setting('first_post'), type: 'boolean', label: t('settings.first_post') }), 140)
    .setting(
      () => ({
        setting: setting('likes_per_pack'),
        type: 'number',
        min: 0,
        label: t('settings.likes_per_pack'),
        help: t('settings.likes_per_pack_help'),
      }),
      130
    )
    .setting(
      () => ({
        setting: setting('extra_cap'),
        type: 'number',
        min: 0,
        max: 20,
        label: t('settings.extra_cap'),
        help: t('settings.extra_cap_help'),
      }),
      120
    )
    .setting(
      () => ({
        setting: setting('trade_min_age'),
        type: 'number',
        min: 0,
        max: 365,
        label: t('settings.trade_min_age'),
        help: t('settings.trade_min_age_help'),
      }),
      110
    )
    .permission(() => ({ permission: 'sticker-book.view', icon: 'fas fa-note-sticky', label: t('permissions.view'), allowGuest: true }), 'view', 60)
    .permission(() => ({ permission: 'sticker-book.collect', icon: 'fas fa-box-open', label: t('permissions.collect') }), 'reply', 60)
    .permission(() => ({ permission: 'sticker-book.trade', icon: 'fas fa-right-left', label: t('permissions.trade') }), 'reply', 59)
    .permission(() => ({ permission: 'sticker-book.react', icon: 'far fa-note-sticky', label: t('permissions.react') }), 'reply', 58),
];
