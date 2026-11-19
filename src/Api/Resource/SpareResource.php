<?php

namespace ErnestDefoe\StickerBook\Api\Resource;

use ErnestDefoe\StickerBook\OwnedSticker;
use ErnestDefoe\StickerBook\Settings;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;

/**
 * Who has a spare of one sticker, to trade with: GET
 * /api/sticker-book-spares?filter[sticker]=ID. Other members the viewer may
 * see, most spares first (Search\SpareSearcher, Search\StickerFilter).
 *
 * @extends AbstractDatabaseResource<OwnedSticker>
 */
class SpareResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'sticker-book-spares';
    }

    public function model(): string
    {
        return OwnedSticker::class;
    }

    /** A row is one member's copies of one sticker: both keys make its id. */
    public function getId(object $model, \Tobyz\JsonApiServer\Context $context): string
    {
        /** @var OwnedSticker $model */
        return $model->user_id.'-'.$model->sticker_id;
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->authenticated()
                ->can(Settings::TRADE)
                ->defaultInclude(['user'])
                ->paginate(24, 24),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Integer::make('spares')
                ->get(fn (OwnedSticker $row) => $row->count - 1),
            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable(),
        ];
    }
}
