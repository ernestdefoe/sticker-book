<?php

namespace ErnestDefoe\StickerBook;

use ErnestDefoe\StickerBook\Storage\Art;

/**
 * The starter sets that ship with the extension (resources/starter): four
 * sets of eight original stickers, so a forum can open packs the day it
 * installs. Each set is added once; the admin can rename, hide or delete them
 * like any other. Their texts come from the locale files (StarterText): rows
 * keep a `starter_key` and store the English text, and members see it in
 * their own language until an admin changes it.
 */
class Starter
{
    private const RARITY = ['common' => 0, 'rare' => 1, 'epic' => 2, 'legendary' => 3];

    public function __construct(private readonly Art $art, private readonly Catalog $catalog, private readonly StarterText $text)
    {
    }

    /** @return int Sets added. */
    public function install(bool $hidden = false): int
    {
        $dir = __DIR__.'/../resources/starter';
        $manifest = json_decode((string) file_get_contents($dir.'/manifest.json'), true);
        $added = 0;

        foreach ($manifest['sets'] ?? [] as $data) {
            $key = $data['slug'];
            $name = $this->text->english($key, 'name');
            if (StickerSet::query()->where('starter_key', $key)->orWhere('name', $name)->exists()) {
                continue;
            }

            $set = new StickerSet();
            $set->starter_key = $key;
            $set->name = $name;
            $set->description = $this->text->english($key, 'description');
            $set->is_hidden = $hidden;
            $set->position = (int) StickerSet::query()->max('position') + 1;
            $set->save();

            foreach ($data['stickers'] as $i => $row) {
                $sticker = new Sticker();
                $sticker->set_id = $set->id;
                $sticker->starter_key = $key.'.'.$row['slug'];
                $sticker->name = $this->text->english($sticker->starter_key, 'name');
                $sticker->rarity = self::RARITY[$row['rarity']] ?? 0;
                $sticker->position = $i;
                $sticker->image = $this->art->store($dir.'/'.$row['file']);
                $sticker->save();
            }
            $added++;
        }

        $this->catalog->forget();

        return $added;
    }
}
