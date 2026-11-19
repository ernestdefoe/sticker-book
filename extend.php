<?php

/*
 * Sticker Book: members collect stickers in an album.
 *
 * Admins make sets of stickers (art + rarity). Members get a free pack each
 * day and extra packs for taking part, open them with a reveal, fill their
 * album set by set (a completed set can award a FoF Badges badge), trade
 * spares with each other, show their rarest stickers on their profile and
 * put stickers on posts.
 *
 * 🚨 The server decides everything: what a pack holds (Packs, random_int),
 * whether a trade goes through (Trades, one transaction, guarded UPDATEs),
 * what an album shows (silhouettes for what a member doesn't have). The
 * browser only asks.
 *
 * forum.js wires up the routes, the pack button, the profile strip and the
 * post stickers; the pages, the reveal and the forms are lazy chunks.
 */

use ErnestDefoe\StickerBook\Admin;
use ErnestDefoe\StickerBook\Api;
use ErnestDefoe\StickerBook\Badges\AwardSetBadges;
use ErnestDefoe\StickerBook\Console\StarterCommand;
use ErnestDefoe\StickerBook\Event\SetsCompleted;
use ErnestDefoe\StickerBook\Exception\StickerException;
use ErnestDefoe\StickerBook\Gdpr\StickerData;
use ErnestDefoe\StickerBook\Http\PageContent;
use ErnestDefoe\StickerBook\Listener\EarnPacks;
use ErnestDefoe\StickerBook\Notification\TradeBlueprint;
use ErnestDefoe\StickerBook\OwnedSticker;
use ErnestDefoe\StickerBook\Search;
use ErnestDefoe\StickerBook\StickerBookServiceProvider;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Resource\PostResource;
use Flarum\Api\Resource\UserResource;
use Flarum\Extend;
use Flarum\Foundation\Paths;
use Flarum\Gdpr\Extend\UserData;
use Flarum\Http\UrlGenerator;
use Flarum\Post\Event\Posted;
use Flarum\Search\Database\DatabaseSearchDriver;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        // 🚨 Not optional: the pages and the reveal are chunks in here, and undeclared chunks 404.
        ->jsDirectory(__DIR__.'/js/dist/forum')
        ->css(__DIR__.'/less/forum.less')
        ->route('/stickers', 'stickers', PageContent::class)
        ->route('/u/{username}/stickers', 'user.stickers'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\ServiceProvider())
        ->register(StickerBookServiceProvider::class),

    // 🚨 String literals, not our own class constants: extend.php is read before our autoloader is guaranteed.
    (new Extend\Filesystem())
        ->disk('ernestdefoe-sticker-book', fn (Paths $paths, UrlGenerator $url) => [
            'root' => "$paths->public/assets/stickers",
            'url' => $url->to('forum')->path('assets/stickers'),
            'visibility' => 'public',
        ]),

    (new Extend\Routes('api'))
        ->get('/sticker-book/me', 'sticker-book.me', Api\StatusController::class)
        ->post('/sticker-book/open', 'sticker-book.open', Api\OpenController::class)
        ->get('/sticker-book/album', 'sticker-book.album', Api\AlbumController::class)
        ->get('/sticker-book/mine', 'sticker-book.mine', Api\MineController::class)
        ->post('/sticker-book/react', 'sticker-book.react', Api\ReactController::class)
        ->post('/sticker-book/gift', 'sticker-book.gift', Admin\GiftController::class)
        ->post('/sticker-book/starter', 'sticker-book.starter', Admin\StarterController::class),

    // Opening a pack, reacting, gifts and the starter sets are actions; the album, the pack status
    // and the reaction picker are read models shaped per viewer (silhouettes for what they don't
    // have): custom endpoints. Sets, stickers, trades, the collectors board and who has a spare
    // are JSON:API resources.
    new Extend\ApiResource(Api\Resource\StickerSetResource::class),
    new Extend\ApiResource(Api\Resource\StickerResource::class),
    new Extend\ApiResource(Api\Resource\TradeResource::class),
    new Extend\ApiResource(Api\Resource\CollectorResource::class),
    new Extend\ApiResource(Api\Resource\SpareResource::class),

    (new Extend\SearchDriver(DatabaseSearchDriver::class))
        ->addSearcher(OwnedSticker::class, Search\SpareSearcher::class)
        ->addFilter(Search\SpareSearcher::class, Search\StickerFilter::class),

    (new Extend\ErrorHandling())
        ->handler(StickerException::class, Api\StickerErrorHandler::class),

    (new Extend\Console())
        ->command(StarterCommand::class),

    (new Extend\Notification())
        ->type(TradeBlueprint::class, ['alert']),

    (new Extend\ApiResource(UserResource::class))
        ->fields(Api\UserFields::class),

    (new Extend\ApiResource(PostResource::class))
        ->fields(Api\PostFields::class),

    (new Extend\ApiResource(ForumResource::class))
        ->fields(Api\ForumFields::class),

    (new Extend\Settings())
        ->default('ernestdefoe-sticker-book.nav', true)
        ->default('ernestdefoe-sticker-book.reactions', true)
        ->default('ernestdefoe-sticker-book.guarantee_rare', true)
        ->default('ernestdefoe-sticker-book.first_post', true)
        ->default('ernestdefoe-sticker-book.pack_size', 5)
        ->default('ernestdefoe-sticker-book.pity', 40)
        ->default('ernestdefoe-sticker-book.likes_per_pack', 5)
        ->default('ernestdefoe-sticker-book.extra_cap', 2)
        ->default('ernestdefoe-sticker-book.trade_min_age', 7)
        ->default('ernestdefoe-sticker-book.odds_common', 70)
        ->default('ernestdefoe-sticker-book.odds_rare', 22)
        ->default('ernestdefoe-sticker-book.odds_epic', 6.5)
        ->default('ernestdefoe-sticker-book.odds_legendary', 1.5)
        ->serializeToForum('stickerNav', 'ernestdefoe-sticker-book.nav', 'boolval'),

    (new Extend\Event())
        ->listen(Posted::class, [EarnPacks::class, 'posted']),

    (new Extend\Conditional())
        ->whenExtensionEnabled('flarum-likes', fn () => [
            (new Extend\Event())
                ->listen(\Flarum\Likes\Event\PostWasLiked::class, [EarnPacks::class, 'liked']),
        ])
        ->whenExtensionEnabled('flarum-gdpr', fn () => [
            (new UserData())->addType(StickerData::class),
        ])
        // A completed set's badge: nothing of FoF Badges loads without it.
        ->whenExtensionEnabled('fof-badges', fn () => [
            (new Extend\Event())
                ->listen(SetsCompleted::class, AwardSetBadges::class),
        ]),
];
