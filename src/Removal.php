<?php

namespace ErnestDefoe\StickerBook;

use ErnestDefoe\StickerBook\Storage\Art;
use Illuminate\Database\ConnectionInterface;

/**
 * Removing stickers (one, or a whole set's): every copy members had and every
 * reaction with them go too, and the owners' distinct counts are redone, in
 * one transaction with a fixed number of queries. The art files are deleted
 * after it commits, so a rolled-back removal never loses art.
 */
class Removal
{
    public function __construct(private readonly ConnectionInterface $db, private readonly Art $art, private readonly Catalog $catalog)
    {
    }

    public function sticker(Sticker $sticker): void
    {
        $this->stickers([$sticker]);
    }

    public function set(StickerSet $set): void
    {
        $this->db->transaction(function () use ($set) {
            $this->stickers($set->stickers()->get()->all());
            $set->delete();
        });
    }

    /** @param list<Sticker> $stickers */
    private function stickers(array $stickers): void
    {
        $ids = array_map(fn (Sticker $sticker) => (int) $sticker->id, $stickers);
        if ($ids) {
            $this->db->transaction(function () use ($ids) {
                $owners = $this->db->table('sticker_owned')->whereIn('sticker_id', $ids)->distinct()->pluck('user_id')->all();
                $this->db->table('sticker_owned')->whereIn('sticker_id', $ids)->delete();
                $this->db->table('sticker_reactions')->whereIn('sticker_id', $ids)->delete();
                Sticker::query()->whereKey($ids)->delete();

                foreach (array_chunk($owners, 500) as $chunk) {
                    $this->recount($chunk);
                }
            });
        }

        $this->db->afterCommit(function () use ($stickers) {
            foreach ($stickers as $sticker) {
                $this->art->delete($sticker->image);
            }
            $this->catalog->forget();
        });
    }

    /**
     * Each member's distinct count, redone in one UPDATE per chunk: a correlated
     * subquery, which the query builder has no form for. Table and column names
     * go through the grammar, so the table prefix is kept.
     *
     * @param list<int> $userIds
     */
    private function recount(array $userIds): void
    {
        $grammar = $this->db->getQueryGrammar();
        $owned = $grammar->wrapTable('sticker_owned');
        $collectors = $grammar->wrapTable('sticker_collectors');
        $userId = $grammar->wrap('user_id');

        $this->db->table('sticker_collectors')->whereIn('user_id', $userIds)->update([
            'unique_count' => $this->db->raw("(SELECT COUNT(*) FROM $owned WHERE $owned.$userId = $collectors.$userId AND $owned.".$grammar->wrap('count').' > 0)'),
        ]);
    }
}
