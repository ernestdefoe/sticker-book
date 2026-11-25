<?php

namespace ErnestDefoe\StickerBook;

use Flarum\Locale\TranslatorInterface;

/**
 * The starter sets' texts (names, descriptions, sticker names) live in the
 * locale files under lib.starter, so a language pack translates them. A
 * starter set or sticker keeps its `starter_key`; each of its texts is shown
 * translated for as long as the stored text is still the shipped English
 * one. Once an admin changes a text, it is theirs and shown as stored.
 */
class StarterText
{
    private const SOURCE_LOCALE = 'en';

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /** The text as the viewer sees it: translated while untouched, else as stored. */
    public function show(?string $key, string $field, ?string $stored): ?string
    {
        if (! $key || $stored !== $this->english($key, $field)) {
            return $stored;
        }

        return $this->translator->trans(self::id($key, $field));
    }

    /** The shipped English text: what an untouched starter row stores. */
    public function english(string $key, string $field): string
    {
        return $this->translator->trans(self::id($key, $field), [], null, self::SOURCE_LOCALE);
    }

    public static function id(string $key, string $field): string
    {
        return "ernestdefoe-sticker-book.lib.starter.$key.$field";
    }
}
