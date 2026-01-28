<?php

namespace App\Helpers;

use App\Models\Setting;

class TextHelper
{
    /**
     * Format text based on display_uppercase setting
     * 
     * @param string|null $text
     * @return string|null
     */
    public static function formatText(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $displayUppercase = Setting::get('display_uppercase', 'false');
        
        if ($displayUppercase === 'true') {
            return mb_strtoupper($text, 'UTF-8');
        }
        
        return $text;
    }

    /**
     * Format array of texts
     * 
     * @param array $texts
     * @return array
     */
    public static function formatArray(array $texts): array
    {
        return array_map([self::class, 'formatText'], $texts);
    }
}
