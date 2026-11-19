<?php

namespace ErnestDefoe\StickerBook\Api;

use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Packs;
use ErnestDefoe\StickerBook\Settings;
use Flarum\Api\Context;
use Flarum\Api\Schema\Attribute;
use Flarum\Api\Schema\Boolean;

/**
 * What the forum needs to know about the viewer. Their permissions are
 * already loaded; the pack status is two small queries, on forum loads only,
 * for members who collect.
 */
class ForumFields
{
    public function __construct(private readonly Settings $settings, private readonly Catalog $catalog, private readonly Packs $packs)
    {
    }

    /** @return list<Attribute> */
    public function __invoke(): array
    {
        return [
            Boolean::make('stickerCanView')
                ->get(fn ($forum, Context $context) => $context->getActor()->can(Settings::VIEW)),
            Boolean::make('stickerCanTrade')
                ->get(fn ($forum, Context $context) => $context->getActor()->can(Settings::TRADE)),
            Boolean::make('stickerCanReact')
                ->get(fn ($forum, Context $context) => $context->getActor()->can(Settings::REACT) && $this->settings->reactions()),
            Attribute::make('stickerStatus')
                ->visible(fn ($forum, Context $context) => ! $context->getActor()->isGuest() && $context->getActor()->can(Settings::COLLECT) && $this->catalog->liveStickers())
                ->get(fn ($forum, Context $context) => $this->packs->status($context->getActor())),
        ];
    }
}
