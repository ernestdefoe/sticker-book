<?php

use Flarum\Database\Migration;

// A starter sticker's set slug and sticker slug, so its name comes from the locale files (StarterText).
return Migration::addColumns('stickers', [
    'starter_key' => ['string', 'length' => 80, 'nullable' => true],
]);
