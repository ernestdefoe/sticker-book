<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

// One sticker per member per post, from their own album.
return Migration::createTable('sticker_reactions', function (Blueprint $table) {
    $table->unsignedInteger('post_id');
    $table->unsignedInteger('user_id');
    $table->unsignedInteger('sticker_id');
    $table->dateTime('created_at');

    $table->primary(['post_id', 'user_id']);
    $table->index('user_id', 'sticker_reactions_user');
    $table->foreign('post_id', 'sticker_reactions_post_fk')->references('id')->on('posts')->cascadeOnDelete();
    $table->foreign('user_id', 'sticker_reactions_user_fk')->references('id')->on('users')->cascadeOnDelete();
    $table->foreign('sticker_id', 'sticker_reactions_sticker_fk')->references('id')->on('stickers')->cascadeOnDelete();
});
