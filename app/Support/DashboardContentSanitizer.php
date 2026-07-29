<?php

namespace App\Support;

class DashboardContentSanitizer
{
    private const ALLOWED_TAGS = '<p><br><a><strong><em><b><i><u><ul><ol><li><h1><h2><h3><h4><h5><h6><img><table><thead><tbody><tr><th><td><div><span><hr><blockquote><figure><figcaption>';

    public static function clean(string $html): string
    {
        $cleaned = strip_tags($html, self::ALLOWED_TAGS);
        $cleaned = preg_replace('/\son\w+\s*=\s*("|\').*?\1/iu', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\s(href|src)\s*=\s*("|\')\s*javascript:[^"\']*\2/iu', '', $cleaned) ?? $cleaned;

        return trim($cleaned);
    }
}
