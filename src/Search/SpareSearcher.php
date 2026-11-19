<?php

namespace ErnestDefoe\StickerBook\Search;

use ErnestDefoe\StickerBook\OwnedSticker;
use Flarum\Search\Database\AbstractSearcher;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/** Spares other members hold, among the members the viewer may see, most first (SpareResource). */
class SpareSearcher extends AbstractSearcher
{
    /** @return Builder<OwnedSticker> */
    public function getQuery(User $actor): Builder
    {
        return OwnedSticker::query()
            ->where('count', '>', 1)
            ->where('user_id', '<>', $actor->id)
            ->whereIn('user_id', User::query()->whereVisibleTo($actor)->select('id'))
            ->orderByDesc('count');
    }
}
