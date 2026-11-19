<?php

namespace ErnestDefoe\StickerBook\Search;

use ErnestDefoe\StickerBook\Catalog;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;

/**
 * filter[sticker]=ID: spares of one sticker. A sticker that is not in a live
 * set (hidden, deleted, no art) has no spares to trade.
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class StickerFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function __construct(private readonly Catalog $catalog)
    {
    }

    public function getFilterKey(): string
    {
        return 'sticker';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        /** @var DatabaseSearchState $state */
        $id = $this->asInt($value);
        $state->getQuery()->where('sticker_id', isset($this->catalog->liveStickers()[$id]) && ! $negate ? $id : 0);
    }
}
