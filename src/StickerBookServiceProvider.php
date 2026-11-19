<?php

namespace ErnestDefoe\StickerBook;

use Flarum\Foundation\AbstractServiceProvider;

class StickerBookServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        // One instance, so every post field of a request queues into the same batch (keyed by the request).
        $this->container->singleton(Reactions::class);
    }
}
