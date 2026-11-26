<?php

namespace ErnestDefoe\StickerBook\Admin;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use ErnestDefoe\StickerBook\Api\Endpoint;
use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Exception\StickerException;
use ErnestDefoe\StickerBook\StarterText;
use ErnestDefoe\StickerBook\Storage\Art;
use ErnestDefoe\StickerBook\Sticker;
use ErnestDefoe\StickerBook\StickerSet;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /api/sticker-book/sets (create), PATCH /api/sticker-book/sets/{id}
 * (name, description, badge, hidden, availability window), DELETE /api/sticker-book/sets/{id}
 * (with its stickers, their art and every copy members had).
 */
class SetController extends Endpoint
{
    public function __construct(private readonly Catalog $catalog, private readonly Art $art, private readonly StarterText $text)
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
            foreach (Sticker::query()->where('set_id', $set->id)->get() as $sticker) {
                $this->art->delete($sticker->image);
                $sticker->delete();
            }
            $set->delete();
        } else {
            $body = $this->body($request);
            if (! $set->exists || array_key_exists('name', $body)) {
                $name = trim((string) ($body['name'] ?? ''));
                if ($name === '' || mb_strlen($name) > 100) {
                    throw new StickerException('sticker_name_invalid');
                }
                // The admin's form shows a starter text translated: sent back unchanged, it stays translated.
                if ($name !== $this->text->show($set->starter_key, 'name', $set->name)) {
                    $set->name = $name;
                }
            }
            if (array_key_exists('description', $body)) {
                $description = mb_substr(trim((string) $body['description']), 0, 255) ?: null;
                if ($description !== $this->text->show($set->starter_key, 'description', $set->description)) {
                    $set->description = $description;
                }
            }
            if (array_key_exists('badgeId', $body)) {
                $set->badge_id = is_numeric($body['badgeId']) && (int) $body['badgeId'] > 0 ? (int) $body['badgeId'] : null;
            }
            if (array_key_exists('hidden', $body)) {
                $set->is_hidden = (bool) $body['hidden'];
            }
            $this->window($set, $body);
            if (! $set->exists) {
                $set->position = (int) StickerSet::query()->max('position') + 1;
            }
            $set->save();
        }

        $this->catalog->forget();

        return ['id' => $set->id];
    }

    /**
     * availableFrom / availableUntil: ISO 8601 times, or empty for no limit.
     * The end must come after the start.
     *
     * @param array<string, mixed> $body
     */
    private function window(StickerSet $set, array $body): void
    {
        foreach (['availableFrom' => 'available_from', 'availableUntil' => 'available_until'] as $field => $column) {
            if (! array_key_exists($field, $body)) {
                continue;
            }
            $value = $body[$field];
            if ($value === null || $value === '') {
                $set->$column = null;
                continue;
            }
            try {
                $set->$column = Carbon::parse((string) $value)->utc();
            } catch (InvalidFormatException) {
                throw new StickerException('sticker_window_invalid');
            }
        }

        if ($set->available_from && $set->available_until && ! $set->available_until->gt($set->available_from)) {
            throw new StickerException('sticker_window_invalid');
        }
    }
}
