<?php

use Illuminate\Database\Schema\Builder;

/*
 * Starter sets added before 1.0.1 get their keys: a set whose stored name is
 * still the shipped English one, and each of its stickers whose name is, so
 * members see them in their own language. Anything an admin renamed keeps
 * its text. The names are the ones 1.0.0 shipped, written out here so this
 * migration matches what those forums stored whatever later releases reword.
 */
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();
        $shipped = [
            'forest-friends' => [
                'name' => 'Forest Friends',
                'stickers' => [
                    'fox' => 'Fox',
                    'owl' => 'Owl',
                    'hedgehog' => 'Hedgehog',
                    'raccoon' => 'Raccoon',
                    'bear-cub' => 'Bear Cub',
                    'bunny' => 'Bunny',
                    'frog' => 'Frog',
                    'golden-stag' => 'Golden Stag',
                ],
            ],
            'space-cadets' => [
                'name' => 'Space Cadets',
                'stickers' => [
                    'rocket' => 'Rocket',
                    'astronaut-helmet' => 'Astronaut Helmet',
                    'ringed-planet' => 'Ringed Planet',
                    'ufo' => 'UFO',
                    'moon-face' => 'Sleepy Moon',
                    'comet' => 'Comet',
                    'alien-buddy' => 'Alien Buddy',
                    'supernova' => 'Supernova',
                ],
            ],
            'snack-attack' => [
                'name' => 'Snack Attack',
                'stickers' => [
                    'taco' => 'Taco',
                    'donut' => 'Donut',
                    'sushi-roll' => 'Sushi Roll',
                    'pizza-slice' => 'Pizza Slice',
                    'coffee-cup' => 'Coffee Cup',
                    'avocado' => 'Avocado',
                    'ice-cream-cone' => 'Ice Cream Cone',
                    'golden-pancake-stack' => 'Golden Pancake Stack',
                ],
            ],
            'forum-life' => [
                'name' => 'Forum Life',
                'stickers' => [
                    'first' => 'First!',
                    'plus-one' => '+1',
                    'bump' => 'Bump',
                    'tldr' => 'TL;DR',
                    'lurker' => 'Lurker',
                    '404' => '404',
                    'mod-hammer' => 'Mod Hammer',
                    'thread-legend' => 'Thread Legend',
                ],
            ],
        ];
        foreach ($shipped as $setKey => $set) {
            $setId = $db->table('sticker_sets')->whereNull('starter_key')->where('name', $set['name'])->value('id');
            if (! $setId) {
                continue;
            }
            $db->table('sticker_sets')->where('id', $setId)->update(['starter_key' => $setKey]);
            foreach ($set['stickers'] as $stickerKey => $name) {
                $db->table('stickers')->where('set_id', $setId)->whereNull('starter_key')->where('name', $name)
                    ->update(['starter_key' => $setKey.'.'.$stickerKey]);
            }
        }
    },
    'down' => function (Builder $schema) {
        $db = $schema->getConnection();
        $db->table('stickers')->update(['starter_key' => null]);
        $db->table('sticker_sets')->update(['starter_key' => null]);
    },
];
