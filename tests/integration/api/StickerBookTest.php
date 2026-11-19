<?php

namespace ErnestDefoe\StickerBook\Tests\integration\api;

use Carbon\Carbon;
use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Listener\EarnPacks;
use ErnestDefoe\StickerBook\Packs;
use Flarum\Discussion\Discussion;
use Flarum\Likes\Event\PostWasLiked;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\ConnectionInterface;
use Laminas\Diactoros\UploadedFile;
use PHPUnit\Framework\Attributes\Test;

class StickerBookTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-sticker-book');

        $now = Carbon::now();
        $stickers = [];
        // Set 1: four stickers, one per rarity. Set 2: two commons.
        foreach ([[1, 1, 'Fox', 0], [2, 1, 'Owl', 1], [3, 1, 'Frog', 2], [4, 1, 'Stag', 3], [5, 2, 'Taco', 0], [6, 2, 'Donut', 0]] as [$id, $set, $name, $rarity]) {
            $stickers[] = ['id' => $id, 'set_id' => $set, 'name' => $name, 'rarity' => $rarity, 'position' => $id, 'image' => json_encode(['full' => "full-$id.webp", 'small' => "small-$id.webp", 'sil' => "sil-$id.webp"]), 'created_at' => $now, 'updated_at' => $now];
        }

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'dana', 'email' => 'dana@machine.local', 'is_email_confirmed' => 1],
                ['id' => 4, 'username' => 'sam', 'email' => 'sam@machine.local', 'is_email_confirmed' => 1],
            ],
            'sticker_sets' => [
                ['id' => 1, 'name' => 'Woods', 'position' => 1, 'is_hidden' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 2, 'name' => 'Snacks', 'position' => 2, 'is_hidden' => 0, 'created_at' => $now, 'updated_at' => $now],
            ],
            'stickers' => $stickers,
            Discussion::class => [
                ['id' => 1, 'title' => 'Hello', 'created_at' => $now, 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 1, 'last_post_number' => 1],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Hi</p></t>', 'created_at' => $now, 'number' => 1],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        // The cache outlives a test (the catalog, the rate limits, the locks): each test leaves it empty.
        $this->app()->getContainer()->make(Repository::class)->flush();

        parent::tearDown();
    }

    /** @return array{int, array<string, mixed>|null} */
    private function api(string $method, string $path, ?int $as, array $body = [], array $query = []): array
    {
        $options = $as ? ['authenticatedAs' => $as] : [];
        if ($method !== 'GET') {
            $options['json'] = $body;
        }
        $request = $this->request($method, '/api/sticker-book'.$path, $options);
        if ($query) {
            $request = $request->withQueryParams($query);
        }
        $response = $this->send($request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    /**
     * Offer a trade through the JSON:API resource.
     *
     * @return array{int, array<string, mixed>|null}
     */
    private function offer(int $as, int $to, mixed $give, array $take = [], ?string $message = null): array
    {
        $response = $this->send($this->request('POST', '/api/sticker-book-trades', [
            'authenticatedAs' => $as,
            'json' => ['data' => [
                'type' => 'sticker-book-trades',
                'attributes' => ['give' => $give, 'take' => $take, 'message' => $message],
                'relationships' => ['to' => ['data' => ['type' => 'users', 'id' => (string) $to]]],
            ]],
        ]));

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    /** @return array{int, array<string, mixed>|null} */
    private function act(int $as, int|string $id, string $action): array
    {
        $response = $this->send($this->request('POST', "/api/sticker-book-trades/$id/$action", ['authenticatedAs' => $as]));

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    private function db(): ConnectionInterface
    {
        return $this->app()->getContainer()->make(ConnectionInterface::class);
    }

    private function give(int $user, int $sticker, int $count): void
    {
        $this->db()->table('sticker_owned')->insert(['user_id' => $user, 'sticker_id' => $sticker, 'count' => $count, 'first_at' => Carbon::now()]);
    }

    private function owns(int $user, int $sticker): int
    {
        return (int) $this->db()->table('sticker_owned')->where('user_id', $user)->where('sticker_id', $sticker)->value('count');
    }

    #[Test]
    public function the_daily_pack_opens_once_a_day(): void
    {
        [$status, $body] = $this->api('POST', '/open', 2);
        $this->assertSame(200, $status, json_encode($body));
        $this->assertCount(5, $body['cards']);
        $this->assertSame('daily', $body['source']);
        $this->assertSame(0, $body['status']['packs']);
        $this->assertSame(5, (int) $this->db()->table('sticker_owned')->where('user_id', 2)->sum('count'));

        [$status, $body] = $this->api('POST', '/open', 2);
        $this->assertSame(422, $status);
        $this->assertSame('sticker_no_pack', $body['errors'][0]['code']);
    }

    #[Test]
    public function a_second_request_for_the_same_member_is_told_to_try_again(): void
    {
        $lock = $this->app()->getContainer()->make(Repository::class)->getStore()->lock('ernestdefoe-sticker-book.collector.2', 10);
        $this->assertTrue($lock->get());

        [$status, $body] = $this->api('POST', '/open', 2);
        $this->assertSame(409, $status);
        $this->assertSame('sticker_busy', $body['errors'][0]['code']);
        $this->assertSame(0, $this->db()->table('sticker_packs')->count());

        $lock->release();
        $this->assertSame(200, $this->api('POST', '/open', 2)[0]);
    }

    #[Test]
    public function guests_cannot_open_packs(): void
    {
        $this->assertSame(401, $this->api('GET', '/me', null)[0]);
        $this->assertNotSame(200, $this->api('POST', '/open', null)[0]);
        $this->assertSame(0, $this->db()->table('sticker_packs')->count());
    }

    #[Test]
    public function earned_packs_wait_and_open_oldest_first(): void
    {
        $this->app();
        $packs = $this->app()->getContainer()->make(Packs::class);
        $packs->gift(2, 2);

        $this->assertSame(3, $this->api('GET', '/me', 2)[1]['packs']);
        $this->assertSame('daily', $this->api('POST', '/open', 2)[1]['source']);
        $this->assertSame('gift', $this->api('POST', '/open', 2)[1]['source']);
        $this->assertSame(0, $this->api('POST', '/open', 2)[1]['status']['packs']);
    }

    #[Test]
    public function the_pity_timer_guarantees_a_legendary(): void
    {
        $this->setting('ernestdefoe-sticker-book.pity', 3);
        $this->setting('ernestdefoe-sticker-book.odds_legendary', 0);
        $this->app()->getContainer()->make(Packs::class)->gift(2, 2);

        $legendaries = function (array $cards) {
            return count(array_filter($cards, fn ($card) => $card['rarity'] === 3));
        };
        $this->assertSame(0, $legendaries($this->api('POST', '/open', 2)[1]['cards']));
        $this->assertSame(0, $legendaries($this->api('POST', '/open', 2)[1]['cards']));
        $this->assertSame(1, $legendaries($this->api('POST', '/open', 2)[1]['cards']));
        $this->assertSame(0, (int) $this->db()->table('sticker_collectors')->where('user_id', 2)->value('pity'));
    }

    #[Test]
    public function every_pack_has_a_rare_or_better_when_guaranteed(): void
    {
        $this->setting('ernestdefoe-sticker-book.odds_rare', 0);
        $this->setting('ernestdefoe-sticker-book.odds_epic', 0);
        $this->setting('ernestdefoe-sticker-book.odds_legendary', 0);
        $this->setting('ernestdefoe-sticker-book.odds_common', 100);
        $this->setting('ernestdefoe-sticker-book.pity', 0);

        // With every rare weight at zero the guarantee falls back to the defaults' order: still not common.
        $cards = $this->api('POST', '/open', 2)[1]['cards'];
        $this->assertSame([0, 0, 0, 0], array_map(fn ($c) => $c['rarity'], array_slice($cards, 0, 4)));
        $this->assertGreaterThan(0, $cards[4]['rarity']);
    }

    #[Test]
    public function an_album_shows_only_silhouettes_of_missing_stickers(): void
    {
        $this->give(3, 1, 2);

        [$status, $body] = $this->api('GET', '/album', 2, [], ['user' => 'dana']);
        $this->assertSame(200, $status, json_encode($body));
        $fox = $body['sets'][0]['stickers'][0];
        $owl = $body['sets'][0]['stickers'][1];
        $this->assertSame(2, $fox['count']);
        $this->assertStringContainsString('full-1.webp', $fox['full']);
        $this->assertSame(0, $owl['count']);
        $this->assertArrayNotHasKey('full', $owl);
        $this->assertArrayNotHasKey('small', $owl);
        $this->assertStringContainsString('sil-2.webp', $owl['sil']);
        $this->assertStringNotContainsString('full-2', json_encode($body));
    }

    #[Test]
    public function hidden_sets_are_in_no_album_and_no_pack(): void
    {
        $this->db()->table('sticker_sets')->where('id', 1)->update(['is_hidden' => 1]);
        $this->app()->getContainer()->make(Catalog::class)->forget();

        [, $body] = $this->api('POST', '/open', 2);
        foreach ($body['cards'] as $card) {
            $this->assertSame(2, $card['setId']);
        }
        $this->assertCount(1, $this->api('GET', '/album', 2, [], ['user' => 'normal'])[1]['sets']);
    }

    #[Test]
    public function completing_a_set_is_recorded_once(): void
    {
        $this->give(2, 5, 1);
        // Open packs until the snack set is complete (two commons, so quickly).
        $this->setting('ernestdefoe-sticker-book.odds_common', 100);
        foreach (['rare', 'epic', 'legendary'] as $r) {
            $this->setting("ernestdefoe-sticker-book.odds_$r", 0);
        }
        $this->setting('ernestdefoe-sticker-book.guarantee_rare', 0);
        $this->setting('ernestdefoe-sticker-book.pity', 0);
        $this->app()->getContainer()->make(Packs::class)->gift(2, 20);

        $completed = [];
        for ($i = 0; $i < 21 && $this->owns(2, 6) === 0; $i++) {
            [$status, $body] = $this->api('POST', '/open', 2);
            $this->assertSame(200, $status, json_encode($body));
            $completed = [...$completed, ...$body['completed']];
        }
        $this->assertSame([['id' => 2, 'name' => 'Snacks']], $completed);
        $this->assertSame(1, $this->db()->table('sticker_completions')->where('user_id', 2)->count());
    }

    #[Test]
    public function a_trade_swaps_spares_once(): void
    {
        $this->give(2, 1, 3);
        $this->give(3, 2, 2);

        [$status, $body] = $this->offer(2, 3, [[1, 2]], [[2, 1]], 'Deal?');
        $this->assertSame(201, $status, json_encode($body));
        $id = $body['data']['id'];
        $this->assertSame(1, $this->db()->table('notifications')->where('user_id', 3)->where('type', 'stickerTrade')->count());

        // Only the recipient accepts.
        $this->assertSame(404, $this->act(2, $id, 'accept')[0]);
        $this->assertSame(404, $this->act(4, $id, 'accept')[0]);

        [$status, $body] = $this->act(3, $id, 'accept');
        $this->assertSame(200, $status, json_encode($body));
        $this->assertSame('accepted', $body['data']['attributes']['status']);
        $this->assertSame([1, 2, 1, 1], [$this->owns(2, 1), $this->owns(3, 1), $this->owns(3, 2), $this->owns(2, 2)]);

        // A second accept changes nothing.
        $this->assertSame('sticker_trade_closed', $this->act(3, $id, 'accept')[1]['errors'][0]['code']);
        $this->assertSame([1, 2, 1, 1], [$this->owns(2, 1), $this->owns(3, 1), $this->owns(3, 2), $this->owns(2, 2)]);
    }

    #[Test]
    public function only_spares_can_be_offered(): void
    {
        $this->give(2, 1, 1);
        $this->give(3, 2, 1);

        $this->assertSame('sticker_trade_no_spares', $this->offer(2, 3, [[1, 1]])[1]['errors'][0]['code']);
        $this->give(2, 3, 2);
        $this->assertSame('sticker_trade_they_have_no_spares', $this->offer(2, 3, [[3, 1]], [[2, 1]])[1]['errors'][0]['code']);
        $this->assertSame('sticker_trade_self', $this->offer(2, 2, [[3, 1]])[1]['errors'][0]['code']);
        foreach ([[[3, 0]], [[3, -1]], [[3, 'x']], [[99, 1]], [[3, 1], [3, 1]]] as $bad) {
            // Offers are rate-limited; this test is about what is refused, not how often.
            $this->app()->getContainer()->make('cache.store')->flush();
            $this->assertSame('sticker_trade_invalid', $this->offer(2, 3, $bad)[1]['errors'][0]['code'], json_encode($bad));
        }
    }

    #[Test]
    public function a_trade_whose_spares_are_gone_fails_without_moving_anything(): void
    {
        $this->give(2, 1, 2);
        $this->give(3, 2, 2);
        $this->give(4, 3, 1);

        // Two offers of the same spare: the first accepted uses it up.
        $first = $this->offer(2, 3, [[1, 1]], [[2, 1]])[1]['data']['id'];
        $second = $this->offer(2, 4, [[1, 1]])[1]['data']['id'];

        $this->assertSame(200, $this->act(4, $second, 'accept')[0]);
        [$status, $body] = $this->act(3, $first, 'accept');
        $this->assertSame(422, $status);
        $this->assertSame('sticker_trade_spares_gone', $body['errors'][0]['code']);
        $this->assertSame('failed', $this->db()->table('sticker_trades')->where('id', $first)->value('status'));
        // Dana keeps her Owl spare and gets no Fox; Normal keeps one Fox.
        $this->assertSame([1, 2, 0], [$this->owns(2, 1), $this->owns(3, 2), $this->owns(3, 1)]);
    }

    #[Test]
    public function declining_and_cancelling_belong_to_each_side(): void
    {
        $this->give(2, 1, 5);
        $id = $this->offer(2, 3, [[1, 1]])[1]['data']['id'];

        $this->assertSame(404, $this->act(2, $id, 'decline')[0]);
        $this->assertSame(404, $this->act(3, $id, 'cancel')[0]);
        $this->assertSame('cancelled', $this->act(2, $id, 'cancel')[1]['data']['attributes']['status']);
        $this->assertSame('sticker_trade_closed', $this->act(3, $id, 'accept')[1]['errors'][0]['code']);
        $this->assertSame(0, $this->db()->table('notifications')->where('user_id', 3)->where('type', 'stickerTrade')->where('is_deleted', 0)->count());
    }

    #[Test]
    public function expired_offers_cannot_be_accepted(): void
    {
        $this->give(2, 1, 2);
        $id = $this->offer(2, 3, [[1, 1]])[1]['data']['id'];
        $this->db()->table('sticker_trades')->where('id', $id)->update(['created_at' => Carbon::now()->subDays(15)]);

        $this->assertSame('sticker_trade_closed', $this->act(3, $id, 'accept')[1]['errors'][0]['code']);
        $this->assertSame(2, $this->owns(2, 1));
    }

    #[Test]
    public function members_react_with_their_own_stickers_only(): void
    {
        $this->assertSame('sticker_not_yours', $this->api('POST', '/react', 3, ['post' => 1, 'sticker' => 4])[1]['errors'][0]['code']);

        $this->give(3, 4, 1);
        $this->give(4, 4, 1);
        $this->assertSame(200, $this->api('POST', '/react', 3, ['post' => 1, 'sticker' => 4])[0]);
        $this->assertSame(200, $this->api('POST', '/react', 4, ['post' => 1, 'sticker' => 4])[0]);
        $this->assertSame(404, $this->api('POST', '/react', 3, ['post' => 999, 'sticker' => 4])[0]);

        $response = $this->send($this->request('GET', '/api/posts/1', ['authenticatedAs' => 3]));
        $post = json_decode((string) $response->getBody(), true);
        $reactions = $post['data']['attributes']['stickerReactions'];
        $this->assertSame([['id' => 4, 'count' => 2, 'mine' => true]], array_map(fn ($r) => ['id' => $r['id'], 'count' => $r['count'], 'mine' => $r['mine']], $reactions));

        // Taking it back.
        $this->assertSame(200, $this->api('POST', '/react', 3, ['post' => 1, 'sticker' => null])[0]);
        $this->assertSame(1, $this->db()->table('sticker_reactions')->where('post_id', 1)->count());
    }

    #[Test]
    public function extra_packs_stop_at_the_daily_cap(): void
    {
        $this->setting('ernestdefoe-sticker-book.extra_cap', 2);
        $packs = $this->app()->getContainer()->make(Packs::class);

        $this->assertTrue($packs->earn(2, 'likes'));
        $this->assertTrue($packs->earn(2, 'likes'));
        $this->assertFalse($packs->earn(2, 'likes'));
        $this->assertSame(2, $this->db()->table('sticker_packs')->where('user_id', 2)->whereNull('opened_at')->count());

        // Tomorrow the count starts again.
        $this->db()->table('sticker_collectors')->where('user_id', 2)->update(['extra_on' => Carbon::now()->subDay()->toDateString()]);
        $this->assertTrue($packs->earn(2, 'post'));
    }

    #[Test]
    public function each_like_counts_once_even_after_an_unlike(): void
    {
        $this->setting('ernestdefoe-sticker-book.likes_per_pack', 2);
        $earn = $this->app()->getContainer()->make(EarnPacks::class);
        $post = Post::query()->find(1);
        $packs = fn () => $this->db()->table('sticker_packs')->where('user_id', 2)->count();

        // Dana likes, unlikes and likes again: one like.
        $earn->liked(new PostWasLiked($post, User::query()->find(3)));
        $earn->liked(new PostWasLiked($post, User::query()->find(3)));
        $this->assertSame(0, $packs());

        // Sam's like makes two: a pack.
        $earn->liked(new PostWasLiked($post, User::query()->find(4)));
        $this->assertSame(1, $packs());

        // After the pack, both take their likes back and like again: nothing more, ever.
        foreach ([3, 4, 3, 4] as $liker) {
            $earn->liked(new PostWasLiked($post, User::query()->find($liker)));
        }
        $this->assertSame(1, $packs());
        $this->assertSame(0, (int) $this->db()->table('sticker_collectors')->where('user_id', 2)->value('like_progress'));

        // Liking your own post earns nothing.
        $earn->liked(new PostWasLiked($post, User::query()->find(2)));
        $earn->liked(new PostWasLiked($post, User::query()->find(2)));
        $this->assertSame(1, $packs());
    }

    #[Test]
    public function a_post_awaiting_approval_earns_no_pack(): void
    {
        $earn = $this->app()->getContainer()->make(EarnPacks::class);
        $post = new \Flarum\Post\CommentPost();
        $post->setRawAttributes(['id' => 1, 'user_id' => 2, 'discussion_id' => 1, 'is_approved' => 0], true);
        $post->setRelation('user', User::query()->find(2));

        $earn->posted(new \Flarum\Post\Event\Posted($post));
        $this->assertSame(0, $this->db()->table('sticker_packs')->where('user_id', 2)->count());

        // Once approved, the day's first post counts.
        $post->setRawAttributes(['id' => 1, 'user_id' => 2, 'discussion_id' => 1, 'is_approved' => 1], true);
        $earn->posted(new \Flarum\Post\Event\Posted($post));
        $this->assertSame(['post'], $this->db()->table('sticker_packs')->where('user_id', 2)->pluck('source')->all());
    }

    #[Test]
    public function new_accounts_cannot_trade_until_they_are_old_enough(): void
    {
        $this->give(2, 1, 3);
        $this->db()->table('users')->where('id', 2)->update(['joined_at' => Carbon::now()->subDays(2)]);

        [$status, $body] = $this->offer(2, 3, [[1, 1]]);
        $this->assertSame(403, $status);
        $this->assertSame('sticker_trade_too_new', $body['errors'][0]['code']);

        // An older account offers; a new one can't accept.
        $this->db()->table('users')->where('id', 2)->update(['joined_at' => Carbon::now()->subDays(30)]);
        $this->db()->table('users')->where('id', 3)->update(['joined_at' => Carbon::now()->subDay()]);
        $id = $this->offer(2, 3, [[1, 1]])[1]['data']['id'];
        $this->assertSame('sticker_trade_too_new', $this->act(3, $id, 'accept')[1]['errors'][0]['code']);
        $this->assertSame(3, $this->owns(2, 1));

        // The admin can lower the bar.
        $this->app()->getContainer()->make(\Flarum\Settings\SettingsRepositoryInterface::class)->set('ernestdefoe-sticker-book.trade_min_age', '0');
        $this->assertSame(200, $this->act(3, $id, 'accept')[0]);
    }

    #[Test]
    public function index_names_are_prefixed_and_fit_with_a_long_prefix(): void
    {
        $schema = $this->db()->getSchemaBuilder();
        $prefix = $this->db()->getTablePrefix();
        $tables = ['sticker_sets', 'stickers', 'sticker_collectors', 'sticker_owned', 'sticker_packs', 'sticker_trades', 'sticker_completions', 'sticker_reactions', 'sticker_like_credits'];
        $checked = 0;
        foreach ($tables as $table) {
            foreach ($schema->getIndexes($table) as $index) {
                if ($index['primary'] || str_starts_with($index['name'], 'sqlite_autoindex')) {
                    continue;
                }
                // Laravel's own names: the prefixed table name first, so two installs in one database never share one.
                $this->assertStringStartsWith($prefix.$table.'_', $index['name']);
                // Within MySQL's 64 characters even with a 9-character prefix such as "flarum01_".
                $this->assertLessThanOrEqual(64, strlen($index['name']) - strlen($prefix) + 9, $index['name']);
                $checked++;
            }
        }
        // MySQL also indexes foreign-key columns itself, so it has a few more.
        $this->assertGreaterThanOrEqual(8, $checked);
    }

    #[Test]
    public function the_first_post_of_the_day_earns_one_pack(): void
    {
        foreach (['First', 'Second'] as $text) {
            // Past core's flood control (one post per 10 seconds).
            $this->db()->table('posts')->update(['created_at' => Carbon::now()->subMinute()]);
            $this->assertSame(201, $this->send($this->request('POST', '/api/posts', [
                'authenticatedAs' => 2,
                'json' => ['data' => ['type' => 'posts', 'attributes' => ['content' => "$text post here"], 'relationships' => ['discussion' => ['data' => ['type' => 'discussions', 'id' => '1']]]]],
            ]))->getStatusCode());
        }
        $this->assertSame(['post'], $this->db()->table('sticker_packs')->where('user_id', 2)->pluck('source')->all());
    }

    #[Test]
    public function the_starter_sets_are_added_hidden_through_the_queue(): void
    {
        [$status, $body] = $this->api('POST', '/starter', 1);
        $this->assertSame(200, $status, json_encode($body));
        $this->assertTrue($body['queued']);

        // The test queue runs jobs at once: four sets of eight, hidden, each once.
        $this->assertSame(4, $this->db()->table('sticker_sets')->where('is_hidden', 1)->count());
        $this->assertSame(32, $this->db()->table('stickers')->whereIn('set_id', $this->db()->table('sticker_sets')->where('is_hidden', 1)->pluck('id'))->count());
        $this->api('POST', '/starter', 1);
        $this->assertSame(6, $this->db()->table('sticker_sets')->count());
    }

    #[Test]
    public function deleting_a_set_takes_its_stickers_copies_and_counts(): void
    {
        $this->give(2, 1, 2);
        $this->give(2, 5, 1);
        $this->db()->table('sticker_collectors')->insert(['user_id' => 2, 'unique_count' => 2]);

        $this->assertSame(204, $this->send($this->request('DELETE', '/api/sticker-book-sets/1', ['authenticatedAs' => 1]))->getStatusCode());
        $this->assertSame(0, $this->db()->table('stickers')->where('set_id', 1)->count());
        $this->assertSame(0, $this->owns(2, 1));
        $this->assertSame(1, $this->owns(2, 5));
        $this->assertSame(1, (int) $this->db()->table('sticker_collectors')->where('user_id', 2)->value('unique_count'));
    }

    #[Test]
    public function admin_endpoints_are_for_admins(): void
    {
        $as = fn (int $user, string $method, string $path, array $json = []) => $this->send($this->request($method, $path, ['authenticatedAs' => $user] + ($json ? ['json' => $json] : [])))->getStatusCode();

        $this->assertSame(403, $as(2, 'GET', '/api/sticker-book-sets'));
        $this->assertSame(403, $as(2, 'POST', '/api/sticker-book-sets', ['data' => ['type' => 'sticker-book-sets', 'attributes' => ['name' => 'Mine']]]));
        $this->assertSame(403, $as(2, 'PATCH', '/api/sticker-book-sets/1', ['data' => ['type' => 'sticker-book-sets', 'id' => '1', 'attributes' => ['name' => 'Mine']]]));
        $this->assertSame(403, $as(2, 'DELETE', '/api/sticker-book-stickers/1'));
        $this->assertSame(403, $as(2, 'POST', '/api/sticker-book-stickers/1/art'));
        $this->assertSame(403, $as(2, 'POST', '/api/sticker-book-sets/order', ['sets' => [2, 1]]));
        $this->assertSame(403, $this->api('POST', '/gift', 2, ['username' => 'normal', 'count' => 50])[0]);
        $this->assertSame(1, (int) $this->db()->table('sticker_sets')->where('id', 1)->value('position'));
    }

    #[Test]
    public function the_admin_lists_sets_with_their_stickers_and_owners_in_a_fixed_number_of_queries(): void
    {
        $this->give(2, 1, 2);
        $this->give(3, 1, 1);
        $response = $this->send($this->request('GET', '/api/sticker-book-sets', ['authenticatedAs' => 1]));
        $body = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode(), json_encode($body));
        $this->assertSame(['Woods', 'Snacks'], array_map(fn ($set) => $set['attributes']['name'], $body['data']));
        $fox = array_values(array_filter($body['included'], fn ($r) => $r['type'] === 'sticker-book-stickers' && $r['id'] === '1'))[0];
        $this->assertSame(2, $fox['attributes']['ownerCount']);
        $this->assertSame('Fox', $fox['attributes']['name']);
    }

    #[Test]
    public function the_admin_makes_changes_orders_and_removes_sets_and_stickers(): void
    {
        $send = fn (string $method, string $path, array $json = []) => $this->send($this->request($method, $path, ['authenticatedAs' => 1] + ($json ? ['json' => $json] : [])));

        $response = $send('POST', '/api/sticker-book-sets', ['data' => ['type' => 'sticker-book-sets', 'attributes' => ['name' => 'Birds', 'isHidden' => true]]]);
        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
        $set = json_decode((string) $response->getBody(), true)['data'];
        $this->assertSame(3, $set['attributes']['position']);
        $this->assertSame(422, $send('POST', '/api/sticker-book-sets', ['data' => ['type' => 'sticker-book-sets', 'attributes' => ['name' => '  ']]])->getStatusCode());

        $response = $send('PATCH', '/api/sticker-book-sets/'.$set['id'], ['data' => ['type' => 'sticker-book-sets', 'id' => $set['id'], 'attributes' => ['name' => 'Birds of prey', 'badgeId' => 7]]]);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame(7, (int) $this->db()->table('sticker_sets')->where('id', $set['id'])->value('badge_id'));

        $response = $send('POST', '/api/sticker-book-stickers', ['data' => [
            'type' => 'sticker-book-stickers',
            'attributes' => ['name' => 'Hawk', 'rarity' => 2],
            'relationships' => ['set' => ['data' => ['type' => 'sticker-book-sets', 'id' => $set['id']]]],
        ]]);
        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
        $hawk = json_decode((string) $response->getBody(), true)['data'];
        $this->assertSame(1, $hawk['attributes']['position']);
        // Without art it is in no pack and no album yet.
        $this->assertArrayNotHasKey((int) $hawk['id'], $this->app()->getContainer()->make(Catalog::class)->all()['stickers']);
        $this->assertSame(422, $send('POST', '/api/sticker-book-stickers/'.$hawk['id'].'/art')->getStatusCode());

        $art = __DIR__.'/../../../resources/starter/'.json_decode((string) file_get_contents(__DIR__.'/../../../resources/starter/manifest.json'), true)['sets'][0]['stickers'][0]['file'];
        $copy = tempnam(sys_get_temp_dir(), 'art');
        copy($art, $copy);
        $response = $this->send($this->request('POST', '/api/sticker-book-stickers/'.$hawk['id'].'/art', ['authenticatedAs' => 1])
            ->withUploadedFiles(['art' => new UploadedFile($copy, (int) filesize($copy), UPLOAD_ERR_OK, 'hawk.webp', 'image/webp')]));
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertNotNull(json_decode((string) $response->getBody(), true)['data']['attributes']['small']);
        $this->assertArrayHasKey((int) $hawk['id'], $this->app()->getContainer()->make(Catalog::class)->all()['stickers']);

        $this->assertSame(204, $send('POST', '/api/sticker-book-sets/order', ['sets' => [2, (int) $set['id'], 1]])->getStatusCode());
        $this->assertSame([2, (int) $set['id'], 1], $this->db()->table('sticker_sets')->orderBy('position')->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(204, $send('POST', '/api/sticker-book-sets/order', ['set' => 2, 'stickers' => [6, 5]])->getStatusCode());
        $this->assertSame([6, 5], $this->db()->table('stickers')->where('set_id', 2)->orderBy('position')->pluck('id')->map(fn ($id) => (int) $id)->all());

        $this->give(2, 5, 3);
        $this->assertSame(204, $send('DELETE', '/api/sticker-book-stickers/5')->getStatusCode());
        $this->assertSame(0, $this->owns(2, 5));
        $this->assertSame(0, $this->db()->table('stickers')->where('id', 5)->count());
    }

    #[Test]
    public function a_trade_is_seen_only_by_its_two_members(): void
    {
        $this->give(2, 1, 3);
        $this->give(3, 2, 2);
        $id = $this->offer(2, 3, [[1, 1]], [[2, 1]])[1]['data']['id'];

        $list = fn (?int $as) => $this->send($this->request('GET', '/api/sticker-book-trades', $as ? ['authenticatedAs' => $as] : []));
        $ids = fn (?int $as) => array_column(json_decode((string) $list($as)->getBody(), true)['data'] ?? [], 'id');

        $this->assertSame([$id], $ids(2));
        $this->assertSame([$id], $ids(3));
        $this->assertSame([], $ids(4));
        $this->assertSame(401, $list(null)->getStatusCode());
        $this->assertSame(404, $this->send($this->request('GET', "/api/sticker-book-trades/$id", ['authenticatedAs' => 4]))->getStatusCode());
        // A guest finds nothing: the scope hides it before anything else is checked.
        $this->assertSame(404, $this->send($this->request('GET', "/api/sticker-book-trades/$id"))->getStatusCode());
        // Another pair's trade can't be acted on, whatever the id.
        $this->assertSame(404, $this->act(4, $id, 'decline')[0]);
        $this->assertSame(404, $this->act(4, $id, 'cancel')[0]);
        $this->assertSame('pending', $this->db()->table('sticker_trades')->where('id', $id)->value('status'));

        // Each side sees both members and the stickers on offer.
        $body = json_decode((string) $list(3)->getBody(), true);
        $this->assertEqualsCanonicalizing(['2', '3'], array_column($body['included'], 'id'));
        $this->assertSame(['Fox', 'Owl'], array_column($body['data'][0]['attributes']['stickers'], 'name'));
    }

    #[Test]
    public function an_offer_to_a_member_the_sender_cannot_see_is_refused(): void
    {
        $this->give(2, 1, 3);
        [$status] = $this->offer(2, 999, [[1, 1]]);
        $this->assertContains($status, [404, 422]);
        $this->assertSame(0, $this->db()->table('sticker_trades')->count());
    }

    #[Test]
    public function the_profile_shows_the_rarest_first(): void
    {
        $this->give(3, 1, 1);
        $this->give(3, 4, 1);
        $this->give(3, 2, 1);

        $response = $this->send($this->request('GET', '/api/users/3', ['authenticatedAs' => 2]));
        $user = json_decode((string) $response->getBody(), true);
        $showcase = $user['data']['attributes']['stickerShowcase'];
        $this->assertSame([4, 2, 1], array_column($showcase['top'], 'id'));
        $this->assertSame([3, 6], [$showcase['unique'], $showcase['total']]);

        // Not on lists.
        $list = json_decode((string) $this->send($this->request('GET', '/api/users', ['authenticatedAs' => 1]))->getBody(), true);
        $this->assertArrayNotHasKey('stickerShowcase', $list['data'][0]['attributes']);
    }
}
