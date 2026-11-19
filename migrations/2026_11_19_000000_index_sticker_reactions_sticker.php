<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/*
 * Removing a sticker deletes its reactions by sticker_id. MySQL indexes a
 * foreign key column itself; PostgreSQL and SQLite don't, so the delete would
 * scan the table there.
 */
return [
    'up' => function (Builder $schema) {
        $schema->table('sticker_reactions', function (Blueprint $table) {
            $table->index('sticker_id');
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('sticker_reactions', function (Blueprint $table) {
            $table->dropIndex(['sticker_id']);
        });
    },
];
