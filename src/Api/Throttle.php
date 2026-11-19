<?php

namespace ErnestDefoe\StickerBook\Api;

use ErnestDefoe\StickerBook\Exception\StickerException;
use Flarum\User\User;
use Illuminate\Cache\RateLimiter;

/**
 * A per-member allowance per minute and per day, counted atomically by
 * Laravel's RateLimiter (cache add + increment). 🚨 Two fixed keys per member
 * and action, never one per minute or per day: the file cache never deletes
 * expired entries, so time-stamped keys would leave a dead file behind for
 * every member every day.
 */
class Throttle
{
    public function __construct(private readonly RateLimiter $limiter)
    {
    }

    public function spend(User $actor, string $what, int $perMinute, int $perDay): void
    {
        $key = 'ernestdefoe-sticker-book.rate.'.$what.'.'.$actor->id;
        if ($this->limiter->tooManyAttempts($key.'.minute', $perMinute) || $this->limiter->tooManyAttempts($key.'.day', $perDay)) {
            throw new StickerException('sticker_slow_down', 429);
        }

        $this->limiter->hit($key.'.minute', 60);
        $this->limiter->hit($key.'.day', 86400);
    }
}
