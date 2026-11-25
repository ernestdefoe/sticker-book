<?php

use Flarum\Database\Migration;

// A starter set's slug, so its texts come from the locale files (StarterText).
return Migration::addColumns('sticker_sets', [
    'starter_key' => ['string', 'length' => 80, 'nullable' => true],
]);
