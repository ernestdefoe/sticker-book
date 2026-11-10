<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/*
 * How many of each sticker a member has. A row means they have at least one:
 * trades only move spares (count - 1), so a row never drops to zero.
 * (sticker_id, count) finds members with a spare of a sticker.
 */
return Migration::createTable('sticker_owned', function (Blueprint $table) {
    $table->unsignedInteger('user_id');
    $table->unsignedInteger('sticker_id');
    $table->unsignedInteger('count')->default(1);
    $table->dateTime('first_at');

    $table->primary(['user_id', 'sticker_id']);
    $table->index(['sticker_id', 'count'], 'sticker_owned_spares');
    $table->foreign('user_id', 'sticker_owned_user_fk')->references('id')->on('users')->cascadeOnDelete();
    $table->foreign('sticker_id', 'sticker_owned_sticker_fk')->references('id')->on('stickers')->cascadeOnDelete();
});
