<?php

namespace ErnestDefoe\StickerBook\Api\Resource;

use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Exception\StickerException;
use ErnestDefoe\StickerBook\Removal;
use ErnestDefoe\StickerBook\Settings;
use ErnestDefoe\StickerBook\Sticker;
use ErnestDefoe\StickerBook\Storage\Art;
use ErnestDefoe\StickerBook\Storage\ArtValidator;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Illuminate\Database\Eloquent\Builder;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * The admin's stickers. A new sticker is made in its set first and gets its
 * art from POST /{id}/art (multipart, like core's avatar upload, since
 * JSON:API carries no files); until then it is in no pack and no album
 * (Catalog skips stickers without art). Admins only.
 *
 * @extends AbstractDatabaseResource<Sticker>
 */
class StickerResource extends AbstractDatabaseResource
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly Art $art,
        private readonly ArtValidator $validator,
        private readonly Removal $removal,
    ) {
    }

    public function type(): string
    {
        return 'sticker-book-stickers';
    }

    public function model(): string
    {
        return Sticker::class;
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Create::make()->admin(),
            Endpoint\Update::make()->admin(),
            Endpoint\Delete::make()->admin(),
            Endpoint\Endpoint::make('sticker-book.art')
                ->route('POST', '/{id}/art')
                ->admin()
                ->action(function (Context $context) {
                    $file = $context->request->getUploadedFiles()['art'] ?? null;
                    if (! $file instanceof UploadedFileInterface || $file->getError() === UPLOAD_ERR_NO_FILE) {
                        throw new StickerException('sticker_art_required');
                    }

                    /** @var Sticker $sticker */
                    $sticker = $context->model;
                    $this->replaceArt($sticker, $file);

                    return $sticker;
                }),
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
            Schema\Integer::make('rarity')
                ->writable()
                ->rule('min:0')
                ->rule('max:'.Settings::LEGENDARY),
            Schema\Integer::make('position'),
            Schema\Str::make('small')
                ->nullable()
                ->get(fn (Sticker $sticker) => $this->art->url($sticker->image, 'small')),
            // Members holding at least one: one aggregate for the whole list, not one query per sticker.
            Schema\Integer::make('ownerCount')
                ->countRelation('owned', fn (Builder $query) => $query->where('count', '>', 0)),
            Schema\Relationship\ToOne::make('set')
                ->type('sticker-book-sets')
                ->writableOnCreate()
                ->requiredOnCreate(),
        ];
    }

    public function creating(object $model, OriginalContext $context): ?object
    {
        /** @var Sticker $model */
        $model->position = (int) Sticker::query()->where('set_id', $model->set_id)->max('position') + 1;
        $model->rarity ??= 0;

        return $model;
    }

    public function saved(object $model, OriginalContext $context): ?object
    {
        $this->catalog->forget();

        return $model;
    }

    /** With its art, every copy members had and every reaction with it (Removal). */
    public function delete(object $model, OriginalContext $context): void
    {
        /** @var Sticker $model */
        $this->removal->sticker($model);
    }

    /** New art is stored first; the old art goes only once the sticker is saved, the new art if it isn't. */
    private function replaceArt(Sticker $sticker, UploadedFileInterface $file): void
    {
        $this->validator->assertImageValid('art', $file);
        $old = $sticker->image;
        $sticker->image = $this->art->store((string) $file->getStream()->getMetadata('uri'));
        try {
            $sticker->save();
        } catch (Throwable $e) {
            $this->art->delete($sticker->image);

            throw $e;
        }
        $this->art->delete($old);
        $this->catalog->forget();
    }
}
