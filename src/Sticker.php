<?php

namespace ErnestDefoe\StickerBook;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $set_id
 * @property string $name
 * @property int $rarity
 * @property array<string, mixed>|null $image
 * @property int $position
 * @property-read StickerSet $set
 */
class Sticker extends AbstractModel
{
    protected $table = 'stickers';

    public $timestamps = true;

    protected $casts = ['set_id' => 'integer', 'rarity' => 'integer', 'position' => 'integer', 'image' => 'array'];

    /** @return BelongsTo<StickerSet, $this> */
    public function set(): BelongsTo
    {
        return $this->belongsTo(StickerSet::class, 'set_id');
    }

    /** @return HasMany<OwnedSticker, $this> */
    public function owned(): HasMany
    {
        return $this->hasMany(OwnedSticker::class, 'sticker_id');
    }
}
