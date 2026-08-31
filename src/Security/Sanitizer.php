<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Security;

class Sanitizer
{
    public static function escape(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function cleanString(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        $value = trim($value);
        $value = strip_tags($value);
        return preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? $value;
    }

    public static function cleanFilename(string $filename): string
    {
        $filename = basename($filename);
        $filename = preg_replace('/[^A-Za-z0-9_\-\. ]/', '', $filename) ?? 'file';
        return trim($filename);
    }
}
