<?php

namespace App\Support;

class HeaderNormalizer
{
    public static function normalize(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = preg_replace('/[\x00-\x1F\x7F\x{00A0}\x{200B}-\x{200D}\x{FEFF}]+/u', ' ', $value) ?? $value;
        $value = trim(mb_strtolower($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '_', $value) ?? $value;
        $value = preg_replace('/_+/', '_', $value) ?? $value;

        return trim($value, '_');
    }
}
