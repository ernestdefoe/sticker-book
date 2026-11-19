<?php

namespace ErnestDefoe\StickerBook\Badges;

/**
 * The badges an admin can give a set. None without FoF Badges; with it,
 * BadgesServiceProvider (registered only when fof-badges is enabled) binds
 * FofBadgeChoices in this one's place.
 */
class BadgeChoices
{
    /** @return list<array{id: int, name: string, icon: ?string}> */
    public function all(): array
    {
        return [];
    }
}
