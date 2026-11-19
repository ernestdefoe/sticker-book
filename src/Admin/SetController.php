<?php

namespace ErnestDefoe\StickerBook\Admin;

use ErnestDefoe\StickerBook\Api\Endpoint;
use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Exception\StickerException;
use ErnestDefoe\StickerBook\Removal;
use ErnestDefoe\StickerBook\StickerSet;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /api/sticker-book/sets (create), PATCH /api/sticker-book/sets/{id}
 * (name, description, badge, hidden), DELETE /api/sticker-book/sets/{id}
 * (with its stickers, their art and every copy members had).
 */
class SetController extends Endpoint
{
    public function __construct(private readonly Catalog $catalog, private readonly Removal $removal)
    {
    }

    protected function run(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertAdmin();

        $id = (int) Arr::get($request->getQueryParams(), 'id');
        $set = $id ? StickerSet::query()->find($id) : new StickerSet();
        if (! $set) {
            throw new StickerException('sticker_not_found', 404);
        }

        if ($request->getMethod() === 'DELETE') {
            $this->removal->set($set);
        } else {
            $body = $this->body($request);
            if (! $set->exists || array_key_exists('name', $body)) {
                $name = trim((string) ($body['name'] ?? ''));
                if ($name === '' || mb_strlen($name) > 100) {
                    throw new StickerException('sticker_name_invalid');
                }
                $set->name = $name;
            }
            if (array_key_exists('description', $body)) {
                $set->description = mb_substr(trim((string) $body['description']), 0, 255) ?: null;
            }
            if (array_key_exists('badgeId', $body)) {
                $set->badge_id = is_numeric($body['badgeId']) && (int) $body['badgeId'] > 0 ? (int) $body['badgeId'] : null;
            }
            if (array_key_exists('hidden', $body)) {
                $set->is_hidden = (bool) $body['hidden'];
            }
            if (! $set->exists) {
                $set->position = (int) StickerSet::query()->max('position') + 1;
            }
            $set->save();
        }

        $this->catalog->forget();

        return ['id' => $set->id];
    }
}
