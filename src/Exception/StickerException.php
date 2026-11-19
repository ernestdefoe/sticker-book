<?php

namespace ErnestDefoe\StickerBook\Exception;

use RuntimeException;

/** A refusal: Api\StickerErrorHandler sends its code, which the browser shows as lib.errors.<code>. */
class StickerException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, public readonly int $status = 422)
    {
        parent::__construct($errorCode);
    }
}
