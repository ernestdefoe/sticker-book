<?php

namespace ErnestDefoe\StickerBook\Api\Resource;

use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Collector;
use ErnestDefoe\StickerBook\Settings;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * The collectors board: the 15 members with the most different stickers,
 * among the members the viewer may see. Read only.
 *
 * @extends AbstractDatabaseResource<Collector>
 */
class CollectorResource extends AbstractDatabaseResource
{
    public function __construct(private readonly Catalog $catalog)
    {
    }

    public function type(): string
    {
        return 'sticker-book-collectors';
    }

    public function model(): string
    {
        return Collector::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        $query->where('unique_count', '>', 0)
            ->whereIn('user_id', User::query()->whereVisibleTo($context->getActor())->select('id'))
            // Ties go to the older account (the lower id): the same order on every database.
            ->orderByDesc('unique_count')
            ->orderBy('user_id');
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->can(Settings::VIEW)
                ->defaultInclude(['user'])
                ->paginate(15, 15),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Integer::make('uniqueCount'),
            // How many there are to collect: from the cached catalog, not a query.
            Schema\Integer::make('total')
                ->get(fn () => count($this->catalog->liveStickers())),
            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable(),
        ];
    }
}
