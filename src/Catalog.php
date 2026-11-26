<?php

namespace ErnestDefoe\StickerBook;

use Carbon\Carbon;
use ErnestDefoe\StickerBook\Storage\Art;
use Flarum\Locale\LocaleManager;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Contracts\Cache\Repository;

/**
 * Every set and sticker, as plain arrays: one cache entry, rebuilt (two
 * queries) after the admin changes anything. Albums, packs, posts and
 * profiles all read from here, so none of them queries the sets.
 *
 * 🚨 `full` (the art) is only ever sent for stickers the viewer may see in
 * full: someone's album shows missing stickers by `sil` alone.
 *
 * Starter texts are translated (StarterText), so there is one entry per
 * locale, built in the language of the request that first needs it.
 */
class Catalog
{
    private const KEY = 'ernestdefoe-sticker-book.catalog';

    /** @var array<string, array{sets: list<array<string, mixed>>, stickers: array<int, array<string, mixed>>}> by locale */
    private array $loaded = [];

    public function __construct(
        private readonly Repository $cache,
        private readonly Art $art,
        private readonly StarterText $text,
        private readonly TranslatorInterface $translator,
        private readonly LocaleManager $locales,
    ) {
    }

    /** @return array{sets: list<array<string, mixed>>, stickers: array<int, array<string, mixed>>} */
    public function all(): array
    {
        $locale = $this->translator->getLocale();

        return $this->loaded[$locale] ??= $this->cache->rememberForever(self::KEY.'.'.$locale, fn () => $this->build());
    }

    public function forget(): void
    {
        $this->loaded = [];
        foreach (array_keys($this->locales->getLocales()) as $locale) {
            $this->cache->forget(self::KEY.'.'.$locale);
        }
    }

    /** @return array<string, mixed>|null */
    public function sticker(int $id): ?array
    {
        return $this->all()['stickers'][$id] ?? null;
    }

    /** @return list<array<string, mixed>> Sets members can see, in order. */
    public function liveSets(): array
    {
        return array_values(array_filter($this->all()['sets'], fn ($set) => ! $set['hidden'] && $set['stickers']));
    }

    /** @return array<int, array<string, mixed>> Stickers that can come out of a pack, by id. */
    public function liveStickers(): array
    {
        $out = [];
        $stickers = $this->all()['stickers'];
        foreach ($this->liveSets() as $set) {
            foreach ($set['stickers'] as $id) {
                $out[$id] = $stickers[$id];
            }
        }

        return $out;
    }

    /**
     * Stickers that can come out of a pack right now: those of live sets whose
     * availability window (if they have one) is open. The window is checked
     * on every call, so the cached catalog never needs rebuilding when one
     * opens or closes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function packableStickers(?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $out = [];
        $stickers = $this->all()['stickers'];
        foreach ($this->liveSets() as $set) {
            if (! self::isOpen($set, $now)) {
                continue;
            }
            foreach ($set['stickers'] as $id) {
                $out[$id] = $stickers[$id];
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $set */
    public static function isOpen(array $set, Carbon $now): bool
    {
        return (! $set['availableFrom'] || $now->gte(Carbon::parse($set['availableFrom'])))
            && (! $set['availableUntil'] || $now->lt(Carbon::parse($set['availableUntil'])));
    }

    /** @return array<string, mixed>|null A sticker as anyone may see it once they have it; null when it is not in a live set. */
    public function card(int $id): ?array
    {
        $s = $this->sticker($id);

        return $s ? ['id' => $s['id'], 'setId' => $s['setId'], 'name' => $s['name'], 'rarity' => $s['rarity'], 'full' => $s['full'], 'small' => $s['small']] : null;
    }

    /** @return array{sets: list<array<string, mixed>>, stickers: array<int, array<string, mixed>>} */
    private function build(): array
    {
        $sets = StickerSet::query()->orderBy('position')->orderBy('id')->get();
        $stickers = Sticker::query()->orderBy('position')->orderBy('id')->get()->groupBy('set_id');

        $out = ['sets' => [], 'stickers' => []];
        foreach ($sets as $set) {
            $ids = [];
            foreach ($stickers[$set->id] ?? [] as $sticker) {
                if (! $sticker->image) {
                    continue;
                }
                $ids[] = $sticker->id;
                $out['stickers'][$sticker->id] = [
                    'id' => $sticker->id,
                    'setId' => $set->id,
                    'name' => $this->text->show($sticker->starter_key, 'name', $sticker->name),
                    'rarity' => $sticker->rarity,
                    'full' => $this->art->url($sticker->image, 'full'),
                    'small' => $this->art->url($sticker->image, 'small'),
                    'sil' => $this->art->url($sticker->image, 'sil'),
                ];
            }
            $out['sets'][] = [
                'id' => $set->id,
                'name' => $this->text->show($set->starter_key, 'name', $set->name),
                'description' => $this->text->show($set->starter_key, 'description', $set->description),
                'badgeId' => $set->badge_id,
                'hidden' => $set->is_hidden,
                'availableFrom' => $set->available_from?->toIso8601String(),
                'availableUntil' => $set->available_until?->toIso8601String(),
                'stickers' => $ids,
            ];
        }

        return $out;
    }
}
