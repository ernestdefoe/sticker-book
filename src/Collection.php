<?php

namespace ErnestDefoe\StickerBook;

use Carbon\Carbon;
use Closure;
use ErnestDefoe\StickerBook\Exception\StickerException;
use ErnestDefoe\StickerBook\Event\SetsCompleted;
use Flarum\User\User;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * What members own. Writes happen inside Collection::exclusive, so one
 * member's packs and trades run one after another.
 */
class Collection
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Repository $cache,
        private readonly Catalog $catalog,
        private readonly Dispatcher $events,
    ) {
    }

    public function owned(): Builder
    {
        return $this->db->table('sticker_owned');
    }

    public function collectors(): Builder
    {
        return $this->db->table('sticker_collectors');
    }

    /**
     * Run $work in a transaction that only one request per member can be in:
     * a pack being opened, a trade going through. A cache lock per member
     * (taken in id order, never waited for: a second request at the same
     * moment is told to try again) makes this hold on every database, since
     * SQLite has no row locks; inside it the collector rows are made when
     * missing and locked for update as well, for MySQL and PostgreSQL.
     *
     * @template T
     * @param list<int> $userIds
     * @param Closure(array<int, object>): T $work Gets the collector rows by member id.
     * @return T
     */
    public function exclusive(array $userIds, Closure $work): mixed
    {
        $userIds = array_values(array_unique($userIds));
        sort($userIds);

        // A cache driver without locks (a custom one) leaves the row locks alone to do it.
        $store = $this->cache->getStore();
        $held = [];
        try {
            foreach ($store instanceof LockProvider ? $userIds : [] as $id) {
                $lock = $store->lock('ernestdefoe-sticker-book.collector.'.$id, 10);
                if (! $lock->get()) {
                    throw new StickerException('sticker_busy', 409);
                }
                $held[] = $lock;
            }

            return $this->db->transaction(function () use ($userIds, $work) {
                $this->collectors()->insertOrIgnore(array_map(fn ($id) => ['user_id' => $id], $userIds));

                return $work($this->collectors()->whereIn('user_id', $userIds)->orderBy('user_id')->lockForUpdate()->get()->keyBy('user_id')->all());
            });
        } finally {
            foreach (array_reverse($held) as $lock) {
                $lock->release();
            }
        }
    }

    /**
     * Add stickers to a member. A row is made with count 0 and then raised,
     * so two additions at once both count.
     *
     * @param array<int, int> $counts sticker id => how many
     * @return list<int> The sticker ids they didn't have before.
     */
    public function add(int $userId, array $counts): array
    {
        if (! $counts) {
            return [];
        }

        $ids = array_keys($counts);
        $had = $this->owned()->where('user_id', $userId)->whereIn('sticker_id', $ids)->where('count', '>', 0)->pluck('sticker_id')->map(fn ($id) => (int) $id)->all();

        $now = Carbon::now();
        $this->owned()->insertOrIgnore(array_map(fn ($id) => ['user_id' => $userId, 'sticker_id' => $id, 'count' => 0, 'first_at' => $now], $ids));

        $byAmount = [];
        foreach ($counts as $id => $n) {
            $byAmount[$n][] = $id;
        }
        foreach ($byAmount as $n => $group) {
            $this->owned()->where('user_id', $userId)->whereIn('sticker_id', $group)->increment('count', $n);
        }

        return array_values(array_diff($ids, $had));
    }

    /**
     * Take spares: only ever leaves at least one, in a guarded UPDATE, so
     * nothing can go below what the member holds.
     */
    public function takeSpares(int $userId, int $stickerId, int $n): bool
    {
        return $this->owned()->where('user_id', $userId)->where('sticker_id', $stickerId)->where('count', '>', $n)->decrement('count', $n) === 1;
    }

    /** @return array<int, int> sticker id => count, for one member. */
    public function countsFor(int $userId): array
    {
        return $this->owned()->where('user_id', $userId)->where('count', '>', 0)->pluck('count', 'sticker_id')->map(fn ($n) => (int) $n)->all();
    }

    /**
     * After stickers arrive: the member's distinct count, and the sets the
     * arrivals completed (recorded once each).
     *
     * @param list<int> $stickerIds what just arrived
     * @return list<int> Sets completed just now.
     */
    public function settle(int $userId, array $stickerIds): array
    {
        $catalog = $this->catalog->all();
        $unique = $this->owned()->where('user_id', $userId)->where('count', '>', 0)->count();
        $this->collectors()->where('user_id', $userId)->update(['unique_count' => $unique]);

        $touched = [];
        foreach ($stickerIds as $id) {
            $setId = $catalog['stickers'][$id]['setId'] ?? null;
            if ($setId) {
                $touched[$setId] = true;
            }
        }

        $completed = [];
        foreach ($this->catalog->liveSets() as $set) {
            if (! isset($touched[$set['id']])) {
                continue;
            }
            $have = $this->owned()->where('user_id', $userId)->whereIn('sticker_id', $set['stickers'])->where('count', '>', 0)->count();
            if ($have === count($set['stickers'])
                && $this->db->table('sticker_completions')->insertOrIgnore(['user_id' => $userId, 'set_id' => $set['id'], 'completed_at' => Carbon::now()]) === 1) {
                $completed[] = (int) $set['id'];
            }
        }

        return $completed;
    }

    /**
     * Tell listeners (FoF Badges, when it is on) which sets a member just
     * completed. Called after the transaction: a listener's problem never
     * undoes a pack or a trade.
     *
     * @param list<int> $setIds
     */
    public function announce(User $user, array $setIds): void
    {
        if ($setIds) {
            $this->events->dispatch(new SetsCompleted($user, $setIds));
        }
    }
}
