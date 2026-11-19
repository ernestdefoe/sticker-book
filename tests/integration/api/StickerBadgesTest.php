<?php

namespace ErnestDefoe\StickerBook\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\Attributes\Test;

/** FoF Badges, when it is on: a completed set's badge, and the badges the admin picks from. */
class StickerBadgesTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-badges', 'ernestdefoe-sticker-book');

        $now = Carbon::now();
        $this->prepareDatabase([
            User::class => [$this->normalUser()],
            'fof_badges' => [
                ['id' => 7, 'name' => 'Woodland ranger', 'slug' => 'woodland-ranger', 'created_at' => $now, 'updated_at' => $now],
            ],
            'sticker_sets' => [
                ['id' => 1, 'name' => 'Woods', 'position' => 1, 'is_hidden' => 0, 'badge_id' => 7, 'created_at' => $now, 'updated_at' => $now],
            ],
            'stickers' => [
                ['id' => 1, 'set_id' => 1, 'name' => 'Fox', 'rarity' => 0, 'position' => 1, 'image' => json_encode(['full' => 'f.webp', 'small' => 's.webp', 'sil' => 'x.webp']), 'created_at' => $now, 'updated_at' => $now],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        // The cache outlives a test (the catalog, the rate limits, the locks): each test leaves it empty.
        $this->app()->getContainer()->make(Repository::class)->flush();

        parent::tearDown();
    }

    #[Test]
    public function completing_a_set_awards_its_badge(): void
    {
        $response = $this->send($this->request('POST', '/api/sticker-book/open', ['authenticatedAs' => 2, 'json' => []]));
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        $db = $this->app()->getContainer()->make(ConnectionInterface::class);
        $this->assertSame(1, $db->table('sticker_completions')->where('user_id', 2)->where('set_id', 1)->count());
        $this->assertSame(1, $db->table('fof_badge_user')->where('user_id', 2)->where('badge_id', 7)->count());
    }
}
