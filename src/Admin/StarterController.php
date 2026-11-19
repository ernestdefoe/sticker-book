<?php

namespace ErnestDefoe\StickerBook\Admin;

use ErnestDefoe\StickerBook\Api\Endpoint;
use ErnestDefoe\StickerBook\Job\InstallStarterSets;
use Flarum\User\User;
use Illuminate\Contracts\Queue\Queue;
use Psr\Http\Message\ServerRequestInterface;

/** POST /api/sticker-book/starter: queue the starter sets (added hidden, for the admin to look over and show). An action, not a resource. */
class StarterController extends Endpoint
{
    public function __construct(private readonly Queue $queue)
    {
    }

    protected function run(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertAdmin();
        $this->queue->push(new InstallStarterSets());

        return ['queued' => true];
    }
}
