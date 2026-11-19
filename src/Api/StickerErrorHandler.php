<?php

namespace ErnestDefoe\StickerBook\Api;

use ErnestDefoe\StickerBook\Exception\StickerException;
use Flarum\Foundation\ErrorHandling\HandledError;

/** A refusal (StickerException) as a JSON:API error whose code the browser turns into a sentence (lib.errors). */
class StickerErrorHandler
{
    public function handle(StickerException $error): HandledError
    {
        return new HandledError($error, $error->errorCode, $error->status);
    }
}
