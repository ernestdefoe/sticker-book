<?php

use Illuminate\Database\Schema\Builder;
use Symfony\Component\Yaml\Yaml;

/*
 * Starter sets added before 1.0.1 get their keys: a set whose stored name is
 * still the shipped English one, and each of its stickers whose name is, so
 * members see them in their own language. Anything an admin renamed keeps
 * its text.
 */
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();
        $english = Yaml::parseFile(__DIR__.'/../locale/en.yml')['ernestdefoe-sticker-book']['lib']['starter'];
        foreach ($english as $setKey => $set) {
            $setId = $db->table('sticker_sets')->whereNull('starter_key')->where('name', $set['name'])->value('id');
            if (! $setId) {
                continue;
            }
            $db->table('sticker_sets')->where('id', $setId)->update(['starter_key' => $setKey]);
            foreach ($set as $stickerKey => $sticker) {
                if (is_array($sticker)) {
                    $db->table('stickers')->where('set_id', $setId)->whereNull('starter_key')->where('name', $sticker['name'])
                        ->update(['starter_key' => $setKey.'.'.$stickerKey]);
                }
            }
        }
    },
    'down' => function (Builder $schema) {
        $db = $schema->getConnection();
        $db->table('stickers')->update(['starter_key' => null]);
        $db->table('sticker_sets')->update(['starter_key' => null]);
    },
];
