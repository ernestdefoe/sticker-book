<?php

namespace ErnestDefoe\StickerBook\Api\Resource;

use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Removal;
use ErnestDefoe\StickerBook\StickerSet;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Illuminate\Database\ConnectionInterface;
use Laminas\Diactoros\Response\EmptyResponse;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * The admin's sets, with their stickers included (hidden ones and stickers
 * still without art too). Admins only: members see sets through their album,
 * which shows silhouettes for what they don't have.
 *
 * @extends AbstractDatabaseResource<StickerSet>
 */
class StickerSetResource extends AbstractDatabaseResource
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly Removal $removal,
        private readonly ConnectionInterface $db,
    ) {
    }

    public function type(): string
    {
        return 'sticker-book-sets';
    }

    public function model(): string
    {
        return StickerSet::class;
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->admin()
                ->defaultInclude(['stickers'])
                ->defaultSort('position'),
            Endpoint\Create::make()->admin(),
            Endpoint\Update::make()->admin(),
            Endpoint\Delete::make()->admin(),
            // {"sets": [3, 1, 2]}: the sets in their new order, in one UPDATE.
            Endpoint\Endpoint::make('sticker-book.order')
                ->route('POST', '/order')
                ->admin()
                ->action(function (Context $context) {
                    $this->reorder((array) ($context->body()['sets'] ?? []));
                    $this->catalog->forget();

                    return [];
                })
                ->response(fn () => new EmptyResponse(204)),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name')
                ->writable()
                ->requiredOnCreate()
                ->rule('max:100')
                ->rule('regex:/\S/'),
            Schema\Str::make('description')
                ->nullable()
                ->writable()
                ->rule('max:255'),
            Schema\Integer::make('badgeId')
                ->nullable()
                ->writable()
                ->rule('min:1'),
            Schema\Boolean::make('isHidden')
                ->writable(),
            Schema\Integer::make('position'),
            Schema\Relationship\ToMany::make('stickers')
                ->type('sticker-book-stickers')
                ->includable(),
        ];
    }

    public function sorts(): array
    {
        return [SortColumn::make('position')];
    }

    public function creating(object $model, OriginalContext $context): ?object
    {
        /** @var StickerSet $model */
        $model->position = (int) StickerSet::query()->max('position') + 1;
        $model->is_hidden ??= false;

        return $model;
    }

    public function saved(object $model, OriginalContext $context): ?object
    {
        $this->catalog->forget();

        return $model;
    }

    /** With its stickers, their art and every copy members had (Removal). */
    public function delete(object $model, OriginalContext $context): void
    {
        /** @var StickerSet $model */
        $this->removal->set($model);
    }

    /**
     * Positions 0, 1, 2… in the order given, as one CASE UPDATE. The ids are
     * cast to integers, so nothing from the request reaches the SQL as text.
     *
     * @param array<mixed> $ids
     */
    private function reorder(array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (! $ids) {
            return;
        }

        $id = $this->db->getQueryGrammar()->wrap('id');
        $cases = implode(' ', array_map(fn (int $key, int $i) => "WHEN $key THEN $i", $ids, array_keys($ids)));
        StickerSet::query()->whereKey($ids)->update(['position' => $this->db->raw("CASE $id $cases END")]);
    }
}
