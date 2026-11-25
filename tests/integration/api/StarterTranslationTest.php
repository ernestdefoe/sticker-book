<?php

namespace ErnestDefoe\StickerBook\Tests\integration\api;

use Carbon\Carbon;
use ErnestDefoe\StickerBook\Catalog;
use Flarum\Locale\LocaleManager;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\Attributes\Test;

/** The starter sets' texts come from the locale files: a language pack translates them until an admin edits one. */
class StarterTranslationTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-sticker-book');

        $now = Carbon::now();
        $image = json_encode(['full' => 'f.webp', 'small' => 's.webp', 'sil' => 'x.webp']);
        $this->prepareDatabase([
            User::class => [['id' => 2, 'username' => 'normal', 'email' => 'normal@machine.local', 'is_email_confirmed' => 1, 'preferences' => json_encode(['locale' => 'de'])]],
            'sticker_sets' => [
                ['id' => 1, 'starter_key' => 'forest-friends', 'name' => 'Forest Friends', 'description' => 'Woodland pals who never miss a picnic.', 'position' => 1, 'is_hidden' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 2, 'starter_key' => null, 'name' => 'Our own', 'description' => null, 'position' => 2, 'is_hidden' => 0, 'created_at' => $now, 'updated_at' => $now],
            ],
            'stickers' => [
                ['id' => 1, 'set_id' => 1, 'starter_key' => 'forest-friends.fox', 'name' => 'Fox', 'rarity' => 0, 'position' => 0, 'image' => $image, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 2, 'set_id' => 1, 'starter_key' => 'forest-friends.owl', 'name' => 'Owl', 'rarity' => 0, 'position' => 1, 'image' => $image, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 3, 'set_id' => 2, 'starter_key' => null, 'name' => 'Mascot', 'rarity' => 0, 'position' => 0, 'image' => $image, 'created_at' => $now, 'updated_at' => $now],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        // The cache outlives a test (the catalog, the rate limits): each test leaves it empty.
        $this->app()->getContainer()->make(Repository::class)->flush();

        parent::tearDown();
    }

    /**
     * A German language pack (only the texts it translates, as a real one
     * would), registered the way Extend\Locales does, with our English beside it.
     */
    private function german(): void
    {
        $locales = $this->app()->getContainer()->make(LocaleManager::class);
        $locales->addTranslations('en', __DIR__.'/../../../locale/en.yml');
        $locales->addLocale('de', 'Deutsch');
        $locales->addTranslations('de', __DIR__.'/../../fixtures/locale/de.yml');
    }

    /** @return array<string, mixed> */
    private function album(): array
    {
        $this->german();
        $response = $this->send($this->request('GET', '/api/sticker-book/album', ['authenticatedAs' => 2])->withQueryParams(['user' => 'normal']));
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return json_decode((string) $response->getBody(), true);
    }

    #[Test]
    public function a_language_pack_translates_the_starter_texts(): void
    {
        $album = $this->album();
        $woods = $album['sets'][0];

        $this->assertSame('Waldfreunde', $woods['name']);
        $this->assertSame('Waldbewohner, die kein Picknick verpassen.', $woods['description']);
        $this->assertSame(['Fuchs', 'Eule'], array_column($woods['stickers'], 'name'));
        // The admin's own set is shown as written.
        $this->assertSame('Our own', $album['sets'][1]['name']);
        $this->assertSame('Mascot', $album['sets'][1]['stickers'][0]['name']);
    }

    #[Test]
    public function an_untranslated_text_falls_back_to_english_and_no_key_reaches_the_page(): void
    {
        $db = $this->app()->getContainer()->make(ConnectionInterface::class);
        $db->table('stickers')->where('id', 2)->update(['starter_key' => 'forest-friends.raccoon', 'name' => 'Raccoon']);

        $body = json_encode($this->album());
        $this->assertStringContainsString('"Raccoon"', $body);
        $this->assertStringNotContainsString('lib.starter', $body);
    }

    #[Test]
    public function a_text_the_admin_edits_is_theirs_and_one_sent_back_unchanged_stays_translated(): void
    {
        // The admin works in German too.
        $this->german();
        $this->app()->getContainer()->make(ConnectionInterface::class)->table('users')->where('id', 1)->update(['preferences' => json_encode(['locale' => 'de'])]);
        $patch = fn (array $body) => $this->send($this->request('PATCH', '/api/sticker-book/sets/1', ['authenticatedAs' => 1, 'json' => $body]));

        // The admin's form shows the German texts; saving them unchanged keeps the set translated.
        $this->assertSame(200, $patch(['name' => 'Waldfreunde', 'description' => 'Waldbewohner, die kein Picknick verpassen.'])->getStatusCode());
        $db = $this->app()->getContainer()->make(ConnectionInterface::class);
        $this->assertSame('Forest Friends', $db->table('sticker_sets')->where('id', 1)->value('name'));

        $this->assertSame(200, $patch(['name' => 'Unser Wald'])->getStatusCode());
        $woods = $this->album()['sets'][0];
        $this->assertSame('Unser Wald', $woods['name']);
        // Only the edited text is theirs: the description is still translated.
        $this->assertSame('Waldbewohner, die kein Picknick verpassen.', $woods['description']);
    }

    #[Test]
    public function starter_sets_from_before_get_their_keys_unless_renamed(): void
    {
        $db = $this->app()->getContainer()->make(ConnectionInterface::class);
        $db->table('sticker_sets')->update(['starter_key' => null]);
        $db->table('stickers')->update(['starter_key' => null]);
        $db->table('stickers')->where('id', 2)->update(['name' => 'Wise owl']);

        $migration = require __DIR__.'/../../../migrations/2026_11_25_000002_key_existing_starter_sets.php';
        $migration['up']($db->getSchemaBuilder());

        $this->assertSame('forest-friends', $db->table('sticker_sets')->where('id', 1)->value('starter_key'));
        $this->assertNull($db->table('sticker_sets')->where('id', 2)->value('starter_key'));
        $this->assertSame('forest-friends.fox', $db->table('stickers')->where('id', 1)->value('starter_key'));
        $this->assertNull($db->table('stickers')->where('id', 2)->value('starter_key'));
        $this->assertNull($db->table('stickers')->where('id', 3)->value('starter_key'));

        $this->app()->getContainer()->make(Catalog::class)->forget();
        $this->assertSame(['Fuchs', 'Wise owl'], array_column($this->album()['sets'][0]['stickers'], 'name'));
    }
}
