<?php

namespace ErnestDefoe\StickerBook\Job;

use ErnestDefoe\StickerBook\Starter;
use Flarum\Queue\AbstractJob;

/** The starter sets, added hidden: 32 images re-encoded three times each, so it runs on the queue, not in the admin's request. */
class InstallStarterSets extends AbstractJob
{
    public function handle(Starter $starter): void
    {
        $starter->install(true);
    }
}
