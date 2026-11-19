<?php

namespace ErnestDefoe\StickerBook;

/**
 * Every fixed cap in one place. These are abuse limits, not tuning knobs: they
 * keep one member from flooding another with offers, hoarding packs, or
 * hammering the endpoints, and none of them is reached by ordinary play.
 * The numbers an admin tunes (pack size, odds, pity, extra packs, trade age)
 * are settings (Settings).
 */
final class Limits
{
    /** Different stickers on one side of an offer. */
    public const TRADE_ITEMS = 8;

    /** Copies of one sticker in one offer. */
    public const TRADE_COPIES = 20;

    /** Offers a member can have waiting at once, and to the same member. */
    public const TRADE_PENDING = 10;

    public const TRADE_PENDING_TO_ONE = 3;

    /** A pending offer older than this has expired (computed, never a cron). */
    public const TRADE_LIFETIME_DAYS = 14;

    /** Unopened packs a member can hold; earning stops at this many. */
    public const UNOPENED_PACKS = 30;

    /** Requests per member, by action: [per minute, per day] (Api\Throttle). */
    public const RATES = [
        'open' => [20, 400],
        'react' => [30, 500],
        'offer' => [6, 60],
        'trade-action' => [20, 300],
    ];
}
