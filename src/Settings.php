<?php

namespace ErnestDefoe\StickerBook;

use Flarum\Settings\SettingsRepositoryInterface;

/** Setting keys, permissions, and the admin's numbers read with their limits. */
final class Settings
{
    public const PREFIX = 'ernestdefoe-sticker-book.';

    public const PACK_SIZE = self::PREFIX.'pack_size';
    public const ODDS = self::PREFIX.'odds_';             // + common, rare, epic, legendary
    public const PITY = self::PREFIX.'pity';               // packs without a legendary before one is certain; 0 = off
    public const GUARANTEE_RARE = self::PREFIX.'guarantee_rare';
    public const FIRST_POST = self::PREFIX.'first_post';   // a pack for the first post of the day
    public const LIKES = self::PREFIX.'likes_per_pack';    // likes received per pack; 0 = off
    public const EXTRA_CAP = self::PREFIX.'extra_cap';     // extra packs a day
    public const REACTIONS = self::PREFIX.'reactions';
    public const TRADE_MIN_AGE = self::PREFIX.'trade_min_age';  // days an account must exist before it trades

    public const VIEW = 'sticker-book.view';
    public const COLLECT = 'sticker-book.collect';
    public const TRADE = 'sticker-book.trade';
    public const REACT = 'sticker-book.react';

    public const RARITIES = ['common', 'rare', 'epic', 'legendary'];
    public const LEGENDARY = 3;

    /** The shipped odds, in percent (one legendary in about 67 stickers before pity): what an all-zero setup rolls with. */
    public const DEFAULT_ODDS = ['common' => 70, 'rare' => 22, 'epic' => 6.5, 'legendary' => 1.5];

    /** Unopened packs a member can hold; earning stops at this many. */
    public const MAX_UNOPENED = 30;

    public function __construct(private readonly SettingsRepositoryInterface $settings)
    {
    }

    public function packSize(): int
    {
        return $this->int(self::PACK_SIZE, 1, 10);
    }

    public function pity(): int
    {
        return $this->int(self::PITY, 0, 1000);
    }

    public function guaranteeRare(): bool
    {
        return (bool) $this->settings->get(self::GUARANTEE_RARE);
    }

    public function firstPost(): bool
    {
        return (bool) $this->settings->get(self::FIRST_POST);
    }

    public function likesPerPack(): int
    {
        return $this->int(self::LIKES, 0, 1000);
    }

    public function extraCap(): int
    {
        return $this->int(self::EXTRA_CAP, 0, 20);
    }

    /** Days an account must be old to trade, so fresh accounts can't funnel stickers to a main one. */
    public function tradeMinAge(): int
    {
        return $this->int(self::TRADE_MIN_AGE, 0, 365);
    }

    public function reactions(): bool
    {
        return (bool) $this->settings->get(self::REACTIONS);
    }

    /**
     * Weights per rarity in hundredths of a percent, for random_int. Defaults
     * are registered in extend.php; an emptied field is 0 (that rarity never
     * drops). All zero can't be rolled, so then the shipped odds apply.
     *
     * @return list<int>
     */
    public function weights(): array
    {
        $weights = [];
        foreach (self::RARITIES as $name) {
            $weights[] = (int) round(max(0, min(100, (float) $this->settings->get(self::ODDS.$name))) * 100);
        }

        return array_sum($weights) > 0 ? $weights : array_map(fn ($v) => (int) round($v * 100), array_values(self::DEFAULT_ODDS));
    }

    /** @return array<string, float> The odds as members see them, in percent of all stickers. */
    public function odds(): array
    {
        $weights = $this->weights();
        $total = array_sum($weights);

        return array_combine(self::RARITIES, array_map(fn ($w) => round($w * 100 / $total, 2), $weights));
    }

    /** Defaults are registered in extend.php; an emptied field reads as 0, kept within its limits. */
    private function int(string $key, int $min, int $max): int
    {
        return max($min, min($max, (int) $this->settings->get($key)));
    }
}
