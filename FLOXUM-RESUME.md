# Sticker Book Floxum fix wave: paused 2026-11-19 (resume after the weekly reset, Nov 26)

Paused on Ernest's "Yes, pause rewrites". Nothing is pushed or tagged; main is still at origin/main (94ed2c4). This branch holds the whole wave, ready to finish. Delete this file before the release.

## Done on this branch (7 fix: commits)
- Floxum's two 1.0.0 findings: routes moved to `Extend.Routes`; no `resolve()` in instance methods.
- Pre-floxum round 1:
  - FoF Badges is behind `Extend\Conditional` (a `SetsCompleted` event, the `AwardSetBadges` listener); flarum/approval was added to optional-dependencies and `suggest`.
  - `Collection::exclusive` takes a cache lock per member, with `lockForUpdate` inside it.
  - All setting defaults live in extend.php, with no second fallbacks.
  - `ForumFields` is an invokable class; rate limits use the atomic `RateLimiter`; the starter sets install through a queued job.
  - Deletes are bulk (`Removal`); functions are under 50 lines; the frontend is fully TypeScript; there are no silent catches.
- Command Center option A ("if it gets flagged, do it"): sets, stickers, trades, the collectors board and the spares list are now JSON:API resources (`src/Api/Resource`).
  - Trades are scoped to their two members; accept, decline and cancel are actions.
  - Sticker art is uploaded to `POST /sticker-book-stickers/{id}/art`.
  - Spares use a searcher plus `filter[sticker]`.
  - The UI goes through `app.store`; the old custom endpoints are gone.
- Pre-floxum round 2:
  - Fixed caps are in `Limits`; `Reactions` is a singleton.
  - The starter set install is atomic and reads files through the filesystem.
  - The deleted-user listener was dropped (the foreign keys cascade).
  - `sticker_reactions` has a `sticker_id` index (new migration 2026_11_19).
  - Comment fixes and one shared rarity list.
  - Failed loads show an error instead of an empty state.

## Verified at fc08b59
- Integration tests: 32 pass on SQLite, MySQL and PostgreSQL. The driver was confirmed each time and the prefix was `dev_`.
- phpstan is clean. check-typings, prettier and the build pass. No functions over 50 lines.
- Query counts: `/posts` 19, `/discussions` 17, `/users` 5. These are flat from 1 to 20 items, for guests and admins.
- forum.js went from 4406 to 4971 bytes gzipped.
- semgrep shows only the CI action tags; gitleaks finds nothing; npm audit reports 0.
- Browser tests on a local SQLite forum (scratchpad, gone after the pause), at 390 px and 1280 px:
  - Admin: created a set, added a sticker with art.
  - Trades: offered then accepted, offered then declined.
  - The board, the spares list and "Propose a trade" from it.
  - The board and the trades list show an error when their requests fail.

## Left to do
1. Run a fresh pre-floxum pass on HEAD. The third pass was stopped by the pause, so nothing has been reviewed since fc08b59. Fix what it finds and repeat until it reports zero findings and predicts 100.
   - Watch items from pass 2, each in 2 files or fewer:
     - `lockForUpdate` (it is paired with the cache lock);
     - the `m.mount` FAB and reveal;
     - page CSS injected as a `<style>` tag;
     - `stickerStatus` costs 2 queries;
     - the trade list shows only the newest 50, so an older pending offer can be hidden;
     - nothing is pruned.
2. Run ship-check again on the final HEAD, with tests on all three databases and a phone pass on any UI that changed (iOS Simulator and Android emulator).
3. Send the Command Center the commit range, the pre-floxum score and the test results, and **wait for approval**.
4. Then:
   - fast-forward main to this branch (without this file), push, release (the drafter bumps it), and run `npm ci` before the dist build;
   - deploy to dev and the showcase (`bin/deploy`), following the rules' deploy order;
   - update the KB article `/kb/ext-sticker-book` (starter sets are now queued, new endpoints, admin badges come from FoF);
   - post the changelog on edonline only (Discuss stays paused);
   - do not request a Floxum review.
- Showcase seed `98-sticker-book.php`: I checked it against the new code and it only uses `gift`, `open`, `countsFor`, `add` and `settle`, which all still exist.
