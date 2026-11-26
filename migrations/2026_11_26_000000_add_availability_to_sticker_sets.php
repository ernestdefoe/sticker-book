<?php

use Flarum\Database\Migration;

// An event set's window: its stickers come out of packs only between these times (either may be open-ended).
return Migration::addColumns('sticker_sets', [
    'available_from' => ['dateTime', 'nullable' => true],
    'available_until' => ['dateTime', 'nullable' => true],
]);
