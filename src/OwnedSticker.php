<?php

namespace ErnestDefoe\StickerBook;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's copies of one sticker (sticker_owned), read for the admin's
 * owner counts (Sticker::owned) and for who has a spare (SpareResource).
 * Collection writes the rows.
 *
 * @property int $user_id
 * @property int $sticker_id
 * @property int $count
 * @property-read User|null $user
 */
class OwnedSticker extends AbstractModel
{
    protected $table = 'sticker_owned';

    /**
     * The table's key is (user_id, sticker_id); Eloquent takes one column. Rows
     * are never saved through this model, and within one sticker user_id is
     * unique, so it breaks ties when spares are listed.
     */
    protected $primaryKey = 'user_id';

    public $timestamps = false;

    public $incrementing = false;

    protected $casts = ['user_id' => 'integer', 'sticker_id' => 'integer', 'count' => 'integer'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
