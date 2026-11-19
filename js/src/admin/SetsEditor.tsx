import app from 'flarum/admin/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Switch from 'flarum/common/components/Switch';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import { call } from '../common/api';

const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`ernestdefoe-sticker-book.admin.sets.${key}`, params);
const RARITIES = ['common', 'rare', 'epic', 'legendary'];
const rarity = (r: number) => app.translator.trans(`ernestdefoe-sticker-book.admin.rarity.${RARITIES[r]}`);
const value = (e: Event) => (e.target as HTMLInputElement).value;

interface AdminSticker {
  id: number;
  name: string;
  rarity: number;
  small: string;
  full: string;
  owners: number;
}

interface AdminSet {
  id: number;
  name: string;
  description: string | null;
  badgeId: number | null;
  hidden: boolean;
  stickers: AdminSticker[];
}

interface AdminData {
  sets: AdminSet[];
  badges: { id: number; name: string }[];
}

interface SetForm {
  name: string;
  description: string;
  badgeId: number | string;
  hidden: boolean;
}

interface StickerForm {
  name: string;
  rarity: number;
  file: File | null;
  preview?: string;
}

/**
 * The sets and their stickers. A set is a draft while hidden: in no pack
 * and no album. Sticker art is uploaded here and stored as WebP (art, a
 * small copy and the silhouette members see until they have it).
 */
export default class SetsEditor extends Component {
  data: AdminData | null = null;
  /** 'set:new', 'set:<id>', 'sticker:<setId>:new', 'sticker:<id>' */
  editing: string | null = null;
  form: Partial<SetForm & StickerForm> = {};
  gift = { username: '', count: 1 as number | string };
  busy: string | null = null;

  oninit(vnode: Mithril.Vnode<ComponentAttrs, this>) {
    super.oninit(vnode);
    this.load();
  }

  load() {
    return call<AdminData>('GET', '/admin')
      .then((data) => (this.data = data))
      .finally(() => m.redraw());
  }

  view() {
    if (!this.data) return <LoadingIndicator />;
    const { sets } = this.data;

    return (
      <div className="Form-group StickerAdmin">
        <h3>{t('title')}</h3>
        <p className="helpText">{t('help')}</p>

        {!sets.length ? (
          <div className="StickerAdmin-set">
            <p>{t('empty')}</p>
            {this.starterButton('Button Button--primary')}
          </div>
        ) : null}

        {sets.map((set, i) => this.set(set, i, sets.length))}

        {this.editing === 'set:new' ? (
          this.setForm(null)
        ) : (
          <div className="ButtonGroup">
            <Button
              className="Button"
              icon="fas fa-plus"
              onclick={() => this.edit('set:new', { name: '', description: '', badgeId: '', hidden: true })}
            >
              {t('add_set')}
            </Button>
            {sets.length ? this.starterButton('Button') : null}
          </div>
        )}

        {this.giftForm()}
      </div>
    );
  }

  starterButton(className: string) {
    return (
      <Button className={className} icon="fas fa-wand-magic-sparkles" loading={this.busy === 'starter'} onclick={() => this.starter()}>
        {t('add_starter')}
      </Button>
    );
  }

  /** Packs for a member, as a gift. */
  giftForm() {
    return [
      <h3>{t('gift_title')}</h3>,
      <p className="helpText">{t('gift_help')}</p>,
      <div className="StickerAdmin-form">
        <div className="StickerAdmin-row">
          <input
            className="FormControl"
            placeholder={extractText(t('gift_username'))}
            value={this.gift.username}
            oninput={(e: InputEvent) => (this.gift.username = value(e))}
          />
          <input
            className="FormControl"
            type="number"
            min="1"
            max="50"
            value={this.gift.count}
            oninput={(e: InputEvent) => (this.gift.count = value(e))}
          />
          <Button
            className="Button"
            icon="fas fa-gift"
            loading={this.busy === 'gift'}
            disabled={!this.gift.username.trim()}
            onclick={() => this.sendGift()}
          >
            {t('gift_send')}
          </Button>
        </div>
      </div>,
    ];
  }

  set(set: AdminSet, i: number, count: number) {
    return (
      <div className={`StickerAdmin-set ${set.hidden ? 'is-hidden' : ''}`} key={set.id}>
        {this.setHead(set, i, count)}
        {set.description ? <p className="helpText">{set.description}</p> : null}
        {this.editing === `set:${set.id}` ? this.setForm(set) : null}

        <div className="StickerAdmin-grid">
          {set.stickers.map((s) => (
            <button
              type="button"
              className={`StickerAdmin-sticker r${s.rarity}`}
              onclick={() => this.edit(`sticker:${s.id}`, { name: s.name, rarity: s.rarity, file: null, preview: s.full })}
            >
              <img src={s.small} alt="" />
              <span className="StickerAdmin-name">{s.name}</span>
              <span className="StickerAdmin-meta">
                {rarity(s.rarity)} · {t('owners', { count: s.owners })}
              </span>
            </button>
          ))}
        </div>
        {set.stickers.map((s) => (this.editing === `sticker:${s.id}` ? this.stickerForm(set, s) : null))}
        {this.editing === `sticker:${set.id}:new` ? (
          this.stickerForm(set, null)
        ) : (
          <Button className="Button" icon="fas fa-plus" onclick={() => this.edit(`sticker:${set.id}:new`, { name: '', rarity: 0, file: null })}>
            {t('add_sticker')}
          </Button>
        )}
      </div>
    );
  }

  /** A set's name and tags, with its move and edit buttons. */
  setHead(set: AdminSet, i: number, count: number) {
    const badge = this.data!.badges.find((b) => b.id === set.badgeId);
    return (
      <div className="StickerAdmin-setHead">
        <h4>{set.name}</h4>
        {set.hidden ? <span className="StickerAdmin-tag">{t('hidden')}</span> : null}
        <span className="StickerAdmin-tag">{t('sticker_count', { count: set.stickers.length })}</span>
        {badge ? <span className="StickerAdmin-tag">{t('badge_tag', { name: badge.name })}</span> : null}
        <Button
          className="Button Button--icon"
          icon="fas fa-arrow-up"
          disabled={i === 0}
          aria-label={extractText(t('move_up'))}
          onclick={() => this.move(i, -1)}
        />
        <Button
          className="Button Button--icon"
          icon="fas fa-arrow-down"
          disabled={i === count - 1}
          aria-label={extractText(t('move_down'))}
          onclick={() => this.move(i, 1)}
        />
        <Button
          className="Button"
          icon="fas fa-pen"
          onclick={() =>
            this.edit(`set:${set.id}`, { name: set.name, description: set.description || '', badgeId: set.badgeId || '', hidden: set.hidden })
          }
        >
          {t('edit')}
        </Button>
      </div>
    );
  }

  setForm(set: AdminSet | null) {
    const f = this.form as SetForm;
    const badges = this.data!.badges;
    return (
      <div className="StickerAdmin-form">
        <div className="StickerAdmin-row">
          <input
            className="FormControl"
            maxlength="100"
            placeholder={extractText(t('name'))}
            value={f.name}
            oninput={(e: InputEvent) => (f.name = value(e))}
          />
          {badges.length ? (
            <select className="FormControl" value={String(f.badgeId || '')} onchange={(e: Event) => (f.badgeId = value(e))}>
              <option value="">{t('no_badge')}</option>
              {badges.map((b) => (
                <option value={String(b.id)}>{t('badge_option', { name: b.name })}</option>
              ))}
            </select>
          ) : null}
        </div>
        <input
          className="FormControl"
          maxlength="255"
          placeholder={extractText(t('description'))}
          value={f.description}
          oninput={(e: InputEvent) => (f.description = value(e))}
        />
        {badges.length ? null : <p className="helpText">{t('badges_help')}</p>}
        <Switch state={!f.hidden} onchange={(on: boolean) => (f.hidden = !on)}>
          {t('visible')}
        </Switch>
        {this.formButtons('set', !f.name.trim(), () => this.saveSet(set), set ? () => this.deleteSet(set) : null)}
      </div>
    );
  }

  stickerForm(set: AdminSet, sticker: AdminSticker | null) {
    const f = this.form as StickerForm;
    return (
      <div className="StickerAdmin-form">
        <div className="StickerAdmin-row">
          {f.preview ? <img className="StickerAdmin-preview" src={f.preview} alt="" /> : null}
          <input
            className="FormControl"
            maxlength="100"
            placeholder={extractText(t('name'))}
            value={f.name}
            oninput={(e: InputEvent) => (f.name = value(e))}
          />
          <select className="FormControl" value={String(f.rarity)} onchange={(e: Event) => (f.rarity = Number(value(e)))}>
            {RARITIES.map((_, r) => (
              <option value={String(r)}>{rarity(r)}</option>
            ))}
          </select>
        </div>
        <label className="helpText">
          {sticker ? t('replace_art') : t('art')}
          <input
            type="file"
            accept="image/png,image/webp,image/jpeg"
            onchange={(e: Event) => {
              f.file = (e.target as HTMLInputElement).files?.[0] || null;
              if (f.file) f.preview = URL.createObjectURL(f.file);
            }}
          />
        </label>
        {this.formButtons(
          'sticker',
          !f.name.trim() || (!sticker && !f.file),
          () => this.saveSticker(set, sticker),
          sticker ? () => this.deleteSticker(sticker) : null
        )}
      </div>
    );
  }

  /** Save, cancel and (for one that exists) delete, under a set's or a sticker's form. */
  formButtons(busy: string, disabled: boolean, save: () => void, remove: (() => void) | null) {
    return (
      <div className="ButtonGroup">
        <Button className="Button Button--primary" loading={this.busy === busy} disabled={disabled} onclick={save}>
          {t('save')}
        </Button>
        <Button className="Button" onclick={() => (this.editing = null)}>
          {t('cancel')}
        </Button>
        {remove ? (
          <Button className="Button Button--danger" icon="fas fa-trash" onclick={remove}>
            {t('delete')}
          </Button>
        ) : null}
      </div>
    );
  }

  edit(key: string, form: SetForm | StickerForm) {
    this.editing = key;
    this.form = form;
  }

  run(busy: string, promise: Promise<unknown>) {
    this.busy = busy;
    return promise
      .then(() => {
        this.editing = null;
        return this.load();
      })
      .catch(() => {
        /* call() already showed the error */
      })
      .finally(() => {
        this.busy = null;
        m.redraw();
      });
  }

  saveSet(set: AdminSet | null) {
    const f = this.form as SetForm;
    const body = { name: f.name, description: f.description, badgeId: f.badgeId ? Number(f.badgeId) : null, hidden: !!f.hidden };
    this.run('set', set ? call('PATCH', `/sets/${set.id}`, body) : call('POST', '/sets', body));
  }

  deleteSet(set: AdminSet) {
    if (!confirm(extractText(t('delete_set_confirm', { name: set.name })))) return;
    this.run('set', call('DELETE', `/sets/${set.id}`));
  }

  saveSticker(set: AdminSet, sticker: AdminSticker | null) {
    const f = this.form as StickerForm;
    const data = new FormData();
    data.append('name', f.name);
    data.append('rarity', String(f.rarity));
    if (!sticker) data.append('set', String(set.id));
    if (f.file) data.append('art', f.file);
    this.run('sticker', call('POST', sticker ? `/stickers/${sticker.id}` : '/stickers', data, { raw: true }));
  }

  deleteSticker(sticker: AdminSticker) {
    if (!confirm(extractText(t('delete_sticker_confirm', { name: sticker.name, count: sticker.owners })))) return;
    this.run('sticker', call('DELETE', `/stickers/${sticker.id}`));
  }

  move(i: number, by: number) {
    const sets = [...this.data!.sets];
    [sets[i], sets[i + by]] = [sets[i + by], sets[i]];
    this.data!.sets = sets;
    call('POST', '/order', { sets: sets.map((s) => s.id) }).catch(() => this.load());
  }

  starter() {
    this.run(
      'starter',
      call<{ queued: boolean }>('POST', '/starter').then(() => app.alerts.show({ type: 'success' }, t('starter_queued')))
    );
  }

  sendGift() {
    this.busy = 'gift';
    call<{ count: number; username: string }>('POST', '/gift', { username: this.gift.username.trim(), count: Number(this.gift.count) || 1 })
      .then((r) => {
        app.alerts.show({ type: 'success' }, t('gift_sent', { count: r.count, username: r.username }));
        this.gift.username = '';
      })
      .catch(() => {
        /* call() already showed the error */
      })
      .finally(() => {
        this.busy = null;
        m.redraw();
      });
  }
}
