<?php

declare(strict_types=1);

namespace App\Support;

class ApplicationFormUploadLimit
{
    public static function describe(): array
    {
        $uploadMax = self::parseIniSize((string) ini_get('upload_max_filesize'));
        $postMax = self::parseIniSize((string) ini_get('post_max_size'));
        $effective = self::effectiveBytes();

        return [
            'upload_max_filesize' => self::formatBytes($uploadMax),
            'post_max_size' => self::formatBytes($postMax),
            'effective_limit' => self::formatBytes($effective),
            'upload_max_filesize_bytes' => $uploadMax,
            'post_max_size_bytes' => $postMax,
            'effective_limit_bytes' => $effective,
        ];
    }

    public static function humanReadableEffectiveLimit(): string
    {
        return self::describe()['effective_limit'];
    }

    public static function effectiveBytes(): int
    {
        $uploadMax = self::parseIniSize((string) ini_get('upload_max_filesize'));
        $postMax = self::parseIniSize((string) ini_get('post_max_size'));
        $limits = array_filter([$uploadMax, $postMax], static fn (int $value): bool => $value > 0);

        if ($limits === []) {
            return 0;
        }

        return min($limits);
    }

    public static function parseIniSize(string $value): int
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return 0;
        }

        $unit = strtolower(substr($normalized, -1));
        $number = (float) $normalized;

        return match ($unit) {
            'g' => (int) round($number * 1024 * 1024 * 1024),
            'm' => (int) round($number * 1024 * 1024),
            'k' => (int) round($number * 1024),
            default => (int) round((float) $normalized),
        };
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return 'server default';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float) $bytes;
        $index = 0;

        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        $precision = $size >= 10 || $index === 0 ? 0 : 1;

        return number_format($size, $precision, ',', '.') . ' ' . $units[$index];
    }
}