<?php

namespace ErnestDefoe\StickerBook;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's collecting state (sticker_collectors), read here for the
 * collectors board. Collection and Packs write the rows.
 *
 * @property int $user_id
 * @property int $unique_count
 * @property-read User|null $user
 */
class Collector extends AbstractModel
{
    protected $table = 'sticker_collectors';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $casts = ['user_id' => 'integer', 'unique_count' => 'integer'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
