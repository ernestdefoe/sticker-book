<?php

namespace ErnestDefoe\StickerBook\Badges;

use FoF\Badges\Badge;

/** FoF Badges' badges, by name. Only bound when fof-badges is enabled. */
class FofBadgeChoices extends BadgeChoices
{
    public function all(): array
    {
        return Badge::query()->orderBy('name')->get(['id', 'name', 'icon'])
            ->map(fn (Badge $badge) => ['id' => (int) $badge->id, 'name' => (string) $badge->name, 'icon' => $badge->icon])
            ->values()->all();
    }
}
