<?php

namespace ErnestDefoe\StickerBook\Badges;

use Flarum\Foundation\AbstractServiceProvider;

/** Registered only when fof-badges is enabled (extend.php), so nothing of FoF Badges loads without it. */
class BadgesServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->bind(BadgeChoices::class, FofBadgeChoices::class);
    }
}
