<?php

namespace ErnestDefoe\StickerBook\Storage;

use Flarum\Foundation\AbstractImageValidator;

/** Sticker art: PNG, WebP, JPEG or GIF up to 4 MB. Transparent PNG or WebP looks best. */
class ArtValidator extends AbstractImageValidator
{
    public function getMaxSize(): int
    {
        return 4096;
    }

    /** @return list<string> */
    protected function getAllowedTypes(): array
    {
        return ['png', 'webp', 'jpeg', 'jpg', 'gif'];
    }
}
