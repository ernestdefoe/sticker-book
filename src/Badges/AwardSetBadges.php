<?php

namespace ErnestDefoe\StickerBook\Badges;

use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Event\SetsCompleted;
use FoF\Badges\Badge;
use FoF\Badges\BadgeAwarder;
use FoF\Badges\UserBadge;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The badge an admin chose for a set, given to the member who completes it.
 * Only listens when fof-badges is enabled (extend.php). It runs after the
 * pack or trade is saved, so a badge problem is logged and never undoes it.
 */
class AwardSetBadges
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly BadgeAwarder $awarder,
        private readonly LoggerInterface $log,
    ) {
    }

    public function handle(SetsCompleted $event): void
    {
        $badgeIds = [];
        foreach ($this->catalog->all()['sets'] as $set) {
            if ($set['badgeId'] && in_array($set['id'], $event->setIds, true)) {
                $badgeIds[$set['id']] = (int) $set['badgeId'];
            }
        }
        if (! $badgeIds) {
            return;
        }

        $badges = Badge::query()->whereIn('id', array_unique($badgeIds))->get()->keyBy('id');
        foreach ($badgeIds as $setId => $badgeId) {
            $badge = $badges->get($badgeId);
            if (! $badge) {
                continue;
            }
            try {
                $this->awarder->award($event->user, $badge, UserBadge::GRANTED_BY_SYSTEM);
            } catch (Throwable $e) {
                $this->log->warning('[sticker-book] Could not award the badge for set '.$setId.': '.$e->getMessage());
            }
        }
    }
}
