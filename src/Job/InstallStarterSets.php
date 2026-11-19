<?php

namespace ErnestDefoe\StickerBook\Job;

use ErnestDefoe\StickerBook\Starter;
use Flarum\Queue\AbstractJob;

/**
 * The starter sets, added hidden: 32 images re-encoded three times each, so it
 * is a job, run by the forum's queue worker when one is configured (on the
 * default sync queue it still runs within the admin's request).
 */
class InstallStarterSets extends AbstractJob
{
    public function handle(Starter $starter): void
    {
        $starter->install(true);
    }
}
