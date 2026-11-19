import app from 'flarum/admin/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Switch from 'flarum/common/components/Switch';
import Icon from 'flarum/common/components/Icon';
import extractText from 'flarum/common/utils/extractText';
import isExtensionEnabled from 'flarum/admin/utils/isExtensionEnabled';
import type { ApiPayloadSingle } from 'flarum/common/Store';
import type Mithril from 'mithril';
import { alertError, call, request } from '../common/api';
import type StickerSet from '../common/models/StickerSet';
import type Sticker from '../common/models/Sticker';

const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`ernestdefoe-sticker-book.admin.sets.${key}`, params);
const RARITIES = ['common', 'rare', 'epic', 'legendary'];
const rarity = (r: number) => app.translator.trans(`ernestdefoe-sticker-book.admin.rarity.${RARITIES[r]}`);
const value = (e: Event) => (e.target as HTMLInputElement).value;

interface Badge {
  id: number;
  name: string;
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
  preview?: string | null;
}

/** FoF Badges' badges, for the badge a completed set awards (none without FoF Badges, or if they can't be had). */
function loadBadges(): Promise<Badge[]> {
  if (!isExtensionEnabled('fof-badges')) return Promise.resolve([]);
  return request<{ data: { id: string; attributes: { name: string } }[] }>('GET', '/badges', undefined, { quiet: true })
    .then((payload) => payload.data.map((b) => ({ id: Number(b.id), name: b.attributes.name })))
    .catch(() => []);
}

/** Sends a sticker's art (multipart: JSON:API carries no files) and puts the sticker as it is now in the store. */
function uploadArt(sticker: Sticker, file: File) {
  const body = new FormData();
  body.append('art', file);
  return request<ApiPayloadSingle>('POST', `/sticker-book-stickers/${sticker.id()}/art`, body, { raw: true }).then((payload) =>
    app.store.pushPayload<Sticker>(payload)
  );
}

/**
 * The sets and their stickers. A set is a draft while hidden: in no pack
 * and no album. Sticker art is uploaded here and stored as WebP (art, a
 * small copy and the silhouette members see until they have it).
 */
export default class SetsEditor extends Component {
  sets: StickerSet[] | null = null;
  badges: Badge[] = [];
  /** 'set:new', 'set:<id>', 'sticker:<setId>:new', 'sticker:<id>' */
  editing: string | null = null;
  form: Partial<SetForm & StickerForm> = {};
  gift = { username: '', count: 1 as number | string };
  busy: string | null = null;

  oninit(vnode: Mithril.Vnode<ComponentAttrs, this>) {
    super.oninit(vnode);
    this.load();
    loadBadges().then((badges) => {
      this.badges = badges;
      m.redraw();
    });
  }

  load() {
    return app.store
      .find<StickerSet[]>('sticker-book-sets', {}, undefined, { background: true, errorHandler: alertError })
      .then((sets) => (this.sets = sets))
      .finally(() => m.redraw());
  }

  view() {
    const sets = this.sets;
    if (!sets) return <LoadingIndicator />;

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

  set(set: StickerSet, i: number, count: number) {
    const stickers = set.stickers();
    return (
      <div className={`StickerAdmin-set ${set.isHidden() ? 'is-hidden' : ''}`} key={set.id()}>
        {this.setHead(set, i, count)}
        {set.description() ? <p className="helpText">{set.description()}</p> : null}
        {this.editing === `set:${set.id()}` ? this.setForm(set) : null}

        <div className="StickerAdmin-grid">{stickers.map((s) => this.stickerTile(s))}</div>
        {stickers.map((s) => (this.editing === `sticker:${s.id()}` ? this.stickerForm(set, s) : null))}
        {this.editing === `sticker:${set.id()}:new` ? (
          this.stickerForm(set, null)
        ) : (
          <Button className="Button" icon="fas fa-plus" onclick={() => this.edit(`sticker:${set.id()}:new`, { name: '', rarity: 0, file: null })}>
            {t('add_sticker')}
          </Button>
        )}
      </div>
    );
  }

  /** A sticker in its set's grid; one still without art (its upload failed) shows an image icon until it has some. */
  stickerTile(s: Sticker) {
    return (
      <button
        type="button"
        className={`StickerAdmin-sticker r${s.rarity()}`}
        onclick={() => this.edit(`sticker:${s.id()}`, { name: s.name(), rarity: s.rarity(), file: null, preview: s.full() })}
      >
        {s.small() ? <img src={s.small()!} alt="" /> : <Icon name="fas fa-image fa-3x" />}
        <span className="StickerAdmin-name">{s.name()}</span>
        <span className="StickerAdmin-meta">
          {rarity(s.rarity())} · {t('owners', { count: s.ownerCount() })}
        </span>
      </button>
    );
  }

  /** A set's name and tags, with its move and edit buttons. */
  setHead(set: StickerSet, i: number, count: number) {
    const badge = this.badges.find((b) => b.id === set.badgeId());
    return (
      <div className="StickerAdmin-setHead">
        <h4>{set.name()}</h4>
        {set.isHidden() ? <span className="StickerAdmin-tag">{t('hidden')}</span> : null}
        <span className="StickerAdmin-tag">{t('sticker_count', { count: set.stickers().length })}</span>
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
            this.edit(`set:${set.id()}`, {
              name: set.name(),
              description: set.description() || '',
              badgeId: set.badgeId() || '',
              hidden: set.isHidden(),
            })
          }
        >
          {t('edit')}
        </Button>
      </div>
    );
  }

  setForm(set: StickerSet | null) {
    const f = this.form as SetForm;
    const badges = this.badges;
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

  stickerForm(set: StickerSet, sticker: Sticker | null) {
    const f = this.form as StickerForm;
    // A new sticker, or one whose art upload failed: it needs an image before it can be saved.
    const needsArt = !sticker?.small();
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
          {needsArt ? t('art') : t('replace_art')}
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
          !f.name.trim() || (needsArt && !f.file),
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

  /** Runs a change, then closes the form and loads the sets again; `failed` runs instead if it is rejected. */
  run(busy: string, promise: Promise<unknown>, failed?: () => unknown) {
    this.busy = busy;
    return promise
      .then(() => {
        this.editing = null;
        return this.load();
      })
      .catch(() => failed?.()) // alertError already showed the error
      .finally(() => {
        this.busy = null;
        m.redraw();
      });
  }

  saveSet(set: StickerSet | null) {
    const f = this.form as SetForm;
    const attributes = { name: f.name, description: f.description || null, badgeId: f.badgeId ? Number(f.badgeId) : null, isHidden: !!f.hidden };
    this.run('set', (set || app.store.createRecord<StickerSet>('sticker-book-sets')).save(attributes, { errorHandler: alertError }));
  }

  deleteSet(set: StickerSet) {
    if (!confirm(extractText(t('delete_set_confirm', { name: set.name() })))) return;
    this.run('set', set.delete(undefined, { errorHandler: alertError }));
  }

  /**
   * A new sticker is made first and then gets its art; an existing one is
   * saved only if its name or rarity changed, then gets any new art. If the
   * art fails on a new sticker, it stays open as that sticker (without art),
   * so the image can be sent again or the sticker deleted.
   */
  saveSticker(set: StickerSet, sticker: Sticker | null) {
    const f = this.form as StickerForm;
    const fields = { name: f.name, rarity: f.rarity };
    let saved = sticker;
    const changed = !sticker || sticker.name() !== f.name || sticker.rarity() !== f.rarity;
    const save = changed
      ? (sticker || app.store.createRecord<Sticker>('sticker-book-stickers')).save(sticker ? fields : { ...fields, relationships: { set } }, {
          errorHandler: alertError,
        })
      : Promise.resolve(sticker);
    const steps = save.then((s) => ((saved = s), f.file ? uploadArt(s, f.file) : s));

    this.run('sticker', steps, () => {
      if (sticker || !saved) return;
      this.edit(`sticker:${saved.id()}`, { ...f });
      return this.load();
    });
  }

  deleteSticker(sticker: Sticker) {
    if (!confirm(extractText(t('delete_sticker_confirm', { name: sticker.name(), count: sticker.ownerCount() })))) return;
    this.run('sticker', sticker.delete(undefined, { errorHandler: alertError }));
  }

  move(i: number, by: number) {
    const sets = [...this.sets!];
    [sets[i], sets[i + by]] = [sets[i + by], sets[i]];
    this.sets = sets;
    request('POST', '/sticker-book-sets/order', { sets: sets.map((s) => s.id()) }).catch(() => this.load());
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
