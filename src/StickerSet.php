<?php

namespace ErnestDefoe\StickerBook;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $badge_id
 * @property int $position
 * @property bool $is_hidden
 * @property string|null $starter_key
 * @property \Carbon\Carbon|null $available_from
 * @property \Carbon\Carbon|null $available_until
 */
class StickerSet extends AbstractModel
{
    protected $table = 'sticker_sets';

    public $timestamps = true;

    protected $casts = ['badge_id' => 'integer', 'position' => 'integer', 'is_hidden' => 'boolean', 'available_from' => 'datetime', 'available_until' => 'datetime'];

    /** @return HasMany<Sticker, $this> */
    public function stickers(): HasMany
    {
        return $this->hasMany(Sticker::class, 'set_id');
    }
}
