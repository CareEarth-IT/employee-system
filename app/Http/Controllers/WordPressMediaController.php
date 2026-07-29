<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WordPressMediaController extends Controller
{
    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'image/avif',
        'image/gif',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function __invoke(Request $request): StreamedResponse
    {
        $path = ltrim((string) $request->query('object'), '/');

        if ($path === ''
            || str_contains($path, '..')
            || str_contains($path, '\\')
            || str_contains($path, "\0")
        ) {
            abort(404);
        }

        $disk = Storage::disk('wordpress_gcs');
        if (! $disk->exists($path)) {
            abort(404);
        }

        $mimeType = $disk->mimeType($path) ?: 'application/octet-stream';
        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            abort(404);
        }

        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            abort(404);
        }

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
