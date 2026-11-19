<?php

namespace ErnestDefoe\StickerBook\Event;

use Flarum\User\User;

/** A member finished these sets (a pack or a trade gave them the last sticker). Dispatched after the transaction. */
class SetsCompleted
{
    /** @param list<int> $setIds */
    public function __construct(public readonly User $user, public readonly array $setIds)
    {
    }
}
