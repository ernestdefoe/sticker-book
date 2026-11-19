<?php

namespace ErnestDefoe\StickerBook;

use Carbon\Carbon;
use ErnestDefoe\StickerBook\Storage\Art;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Filesystem\Filesystem;
use Throwable;

/**
 * The starter sets that ship with the extension (resources/starter): four
 * sets of eight original stickers, so a forum can open packs the day it
 * installs. Each set is added once (matched by name); the admin can rename,
 * hide or delete them like any other.
 */
class Starter
{
    public function __construct(
        private readonly Art $art,
        private readonly Catalog $catalog,
        private readonly Filesystem $files,
        private readonly ConnectionInterface $db,
    )
    {
    }

    /** @return int Sets added. */
    public function install(bool $hidden = false): int
    {
        $dir = __DIR__.'/../resources/starter';
        $added = 0;

        foreach ($this->files->json($dir.'/manifest.json')['sets'] ?? [] as $data) {
            if (! StickerSet::query()->where('name', $data['name'])->exists()) {
                $this->add($data, $dir, $hidden);
                $added++;
            }
        }

        $this->catalog->forget();

        return $added;
    }

    /**
     * One set: its art stored first, then the set and its stickers in one
     * transaction. If anything fails, nothing of the set is kept (its stored
     * art is removed too), so a later run adds it again.
     *
     * @param array{name: string, description?: string, stickers: list<array{name: string, rarity: string, file: string}>} $data
     */
    private function add(array $data, string $dir, bool $hidden): void
    {
        $rarity = array_flip(Settings::RARITIES);
        $images = [];
        try {
            foreach ($data['stickers'] as $row) {
                $images[] = $this->art->store($dir.'/'.$row['file']);
            }

            $this->db->transaction(function () use ($data, $hidden, $images, $rarity) {
                $set = new StickerSet();
                $set->name = $data['name'];
                $set->description = $data['description'] ?? null;
                $set->is_hidden = $hidden;
                $set->position = (int) StickerSet::query()->max('position') + 1;
                $set->save();

                // The set's stickers in one insert.
                $now = Carbon::now();
                Sticker::query()->insert(array_map(fn (array $row, int $i) => [
                    'set_id' => $set->id,
                    'name' => $row['name'],
                    'rarity' => $rarity[$row['rarity']] ?? 0,
                    'position' => $i,
                    'image' => json_encode($images[$i]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $data['stickers'], array_keys($data['stickers'])));
            });
        } catch (Throwable $e) {
            foreach ($images as $image) {
                $this->art->delete($image);
            }

            throw $e;
        }
    }
}
