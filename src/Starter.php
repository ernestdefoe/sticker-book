<?php

namespace ErnestDefoe\StickerBook;

use Carbon\Carbon;
use ErnestDefoe\StickerBook\Storage\Art;

/**
 * The starter sets that ship with the extension (resources/starter): four
 * sets of eight original stickers, so a forum can open packs the day it
 * installs. Each set is added once (matched by name); the admin can rename,
 * hide or delete them like any other.
 */
class Starter
{
    private const RARITY = ['common' => 0, 'rare' => 1, 'epic' => 2, 'legendary' => 3];

    public function __construct(private readonly Art $art, private readonly Catalog $catalog)
    {
    }

    /** @return int Sets added. */
    public function install(bool $hidden = false): int
    {
        $dir = __DIR__.'/../resources/starter';
        $manifest = json_decode((string) file_get_contents($dir.'/manifest.json'), true);
        $added = 0;

        foreach ($manifest['sets'] ?? [] as $data) {
            if (StickerSet::query()->where('name', $data['name'])->exists()) {
                continue;
            }

            $set = new StickerSet();
            $set->name = $data['name'];
            $set->description = $data['description'] ?? null;
            $set->is_hidden = $hidden;
            $set->position = (int) StickerSet::query()->max('position') + 1;
            $set->save();

            // The set's stickers in one insert (the art is stored first, one file each).
            $now = Carbon::now();
            $rows = [];
            foreach ($data['stickers'] as $i => $row) {
                $rows[] = [
                    'set_id' => $set->id,
                    'name' => $row['name'],
                    'rarity' => self::RARITY[$row['rarity']] ?? 0,
                    'position' => $i,
                    'image' => json_encode($this->art->store($dir.'/'.$row['file'])),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            Sticker::query()->insert($rows);
            $added++;
        }

        $this->catalog->forget();

        return $added;
    }
}
