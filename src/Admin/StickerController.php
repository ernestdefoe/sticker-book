<?php

namespace ErnestDefoe\StickerBook\Admin;

use ErnestDefoe\StickerBook\Api\Endpoint;
use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Exception\StickerException;
use ErnestDefoe\StickerBook\Removal;
use ErnestDefoe\StickerBook\Settings;
use ErnestDefoe\StickerBook\Storage\Art;
use ErnestDefoe\StickerBook\Storage\ArtValidator;
use ErnestDefoe\StickerBook\Sticker;
use ErnestDefoe\StickerBook\StickerSet;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

/**
 * POST /api/sticker-book/stickers (multipart: set, name, rarity, art) adds a
 * sticker; POST /api/sticker-book/stickers/{id} changes it (new art is
 * optional; multipart only arrives on POST); DELETE removes it with its art
 * and every copy members had.
 */
class StickerController extends Endpoint
{
    public function __construct(private readonly Catalog $catalog, private readonly Art $art, private readonly ArtValidator $validator, private readonly Removal $removal)
    {
    }

    protected function run(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertAdmin();

        $id = (int) Arr::get($request->getQueryParams(), 'id');
        $sticker = $id ? Sticker::query()->find($id) : new Sticker();
        if (! $sticker) {
            throw new StickerException('sticker_not_found', 404);
        }

        if ($request->getMethod() === 'DELETE') {
            $this->removal->sticker($sticker);

            return ['id' => $id];
        }

        $this->fill($sticker, $this->body($request));
        $this->saveWithArt($sticker, $request->getUploadedFiles()['art'] ?? null);
        $this->catalog->forget();

        return ['id' => $sticker->id];
    }

    /** @param array<string, mixed> $body */
    private function fill(Sticker $sticker, array $body): void
    {
        if (! $sticker->exists) {
            $set = StickerSet::query()->find((int) ($body['set'] ?? 0));
            if (! $set) {
                throw new StickerException('sticker_not_found', 404);
            }
            $sticker->set_id = $set->id;
            $sticker->position = (int) Sticker::query()->where('set_id', $set->id)->max('position') + 1;
        }
        if (! $sticker->exists || array_key_exists('name', $body)) {
            $name = trim((string) ($body['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 100) {
                throw new StickerException('sticker_name_invalid');
            }
            $sticker->name = $name;
        }
        if (array_key_exists('rarity', $body)) {
            $sticker->rarity = max(0, min(Settings::LEGENDARY, (int) $body['rarity']));
        }
    }

    /** New art is stored first; the old art goes only once the sticker is saved, the new art if it isn't. */
    private function saveWithArt(Sticker $sticker, ?UploadedFileInterface $file): void
    {
        if (! $file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            if (! $sticker->exists) {
                throw new StickerException('sticker_art_required');
            }
            $sticker->save();

            return;
        }

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
    }
}
