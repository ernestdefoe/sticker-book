<?php

namespace ErnestDefoe\StickerBook;

use Flarum\Database\AbstractModel;

/**
 * @property int $id
 * @property int $set_id
 * @property string $name
 * @property int $rarity
 * @property array<string, mixed>|null $image
 * @property int $position
 */
class Sticker extends AbstractModel
{
    protected $table = 'stickers';

    public $timestamps = true;

    protected $casts = ['set_id' => 'integer', 'rarity' => 'integer', 'position' => 'integer', 'image' => 'array'];
}
