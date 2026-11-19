# Sticker Book: working rules

- **Commit straight to `main` and push. Never open a pull request on this repo.** Commit subjects are conventional (`feat:`, `fix:`); the release drafter bumps from the subject.
- `js/dist` is committed and is what forums load. After any change under `js/src` or `less/pages.less`, run `npm run build` in `js/` (from a fresh `npm ci`) and commit the bundle; CI fails if it is stale.
- Publishing a release is Ernest's click. Nothing is announced until he says so.

## Design rules that exist for a reason

- **The server decides every pack.** `Packs::open` takes nothing from the browser: it claims the daily pack (guarded UPDATE on `daily_on`) or the oldest earned one, marks it opened in a guarded UPDATE, and rolls with `random_int` inside `Collection::exclusive`: a cache lock per member (taken with `get()`, never waited for: a second request gets `sticker_busy`) around one transaction that also row-locks the collector rows. Never accept a pack id, a sticker or a result from the client.
- **Pity and guarantees go in the last slot** (`Packs::roll`). Cards are returned rarest last, each with its running count (×3 then ×4 for two copies).
- **Trades move spares only.** `Collection::takeSpares` is `count > n` → `decrement n`, so an owned row never reaches zero. Nothing is held while an offer waits; `Trades::accept` runs in `Collection::exclusive` for both members (locks in id order), moves the trade pending → accepted in a guarded UPDATE, and throws to roll back if any spare is gone (then marks it `failed`). Expiry is computed (`Trade::LIFETIME_DAYS`), never a cron.
- **Missing stickers are silhouettes.** `Storage\Art` writes three WebPs with unrelated random names; `AlbumController` sends `full`/`small` only for owned stickers and `sil` otherwise. Never send the catalog's art URLs wholesale to the forum.
- **The catalog is one cache entry** (`Catalog`), rebuilt after any admin change (`forget()`). Albums, packs, posts and profiles read it instead of querying sets.
- **Extra packs** (first post of the day, likes) go through `Packs::earn`: daily cap and the unopened limit in guarded UPDATEs. A like counts toward `like_progress` once per (post, liker), ever: `sticker_like_credits` is kept on unlike, so like/unlike/like earns nothing. A first post still awaiting approval earns nothing. Trading needs an account at least `trade_min_age` days old (default 7) to offer or accept.
- **Post reactions are one query per page** (`Reactions::deferred`, WeakMap by request). `stickerShowcase` only on the user show endpoint, `stickerStatus` only on the forum payload for members who collect (2 queries).
- **Notification subjects are Users**; the trade id rides in the data.
- **Sets, stickers and trades are JSON:API resources** (`Api\Resource`): sets and stickers are admin-only (art arrives on `POST /sticker-book-stickers/{id}/art`; a sticker without art is in no pack), trades are scoped to their two members (anyone else gets not found) with accept/decline/cancel as actions. The rest (`/api/sticker-book/*`: open, album, board, spares, me, mine, react, gift, starter) are actions or per-viewer read models. Refusals are `StickerException` → `StickerErrorHandler` (code = `lib.errors.*` key).
- **FoF Badges only through `Extend\Conditional`**: `Collection::announce` dispatches `SetsCompleted`; `Badges\AwardSetBadges` listens only when fof-badges is on. The admin reads FoF's own `/api/badges`.
- **Setting defaults live in extend.php only** (`Extend\Settings()->default()`); `Settings` reads without a second fallback.
- LESS evaluates `min()`/`max()`: escape them. Pages, the reveal and the windows are lazy chunks with their CSS compiled into a chunk (`js/tools/css.mjs`); forum.js only wires up routes, the header/phone pack button, the profile strip and post stickers.
- On phones the header's buttons are in the drawer, so a waiting pack also shows as a floating button (`.StickerFab`), lifted above Mobile Tab's bar (`html.has-mobile-tab`).

## Testing

- `composer test:setup && composer test` (30 integration tests; set `DB_DRIVER`, and a `FLARUM_TEST_TMP_DIR_LOCAL` per driver, for MySQL/PostgreSQL), `composer analyse:phpstan`.
- `cd js && npm ci && npm run build && npm run format-check`.
- Starter art: SVG sources in `art/` (not shipped), the WebPs in `resources/starter/` are what installs. The showcase seed is `~/github/showcase/seeds/98-sticker-book.php`.
