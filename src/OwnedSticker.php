<?php

namespace ErnestDefoe\StickerBook;

use Flarum\Database\AbstractModel;

/**
 * A member's copies of one sticker (sticker_owned). Only read through
 * Sticker::owned() for the admin's owner counts; Collection writes the rows.
 *
 * @property int $user_id
 * @property int $sticker_id
 * @property int $count
 */
class OwnedSticker extends AbstractModel
{
    protected $table = 'sticker_owned';

    public $timestamps = false;

    public $incrementing = false;
}
