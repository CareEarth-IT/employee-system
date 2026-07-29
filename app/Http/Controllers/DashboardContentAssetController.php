<?php

namespace App\Http\Controllers;

use App\Services\DashboardContentStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardContentAssetController extends Controller
{
    public function show(Request $request, string $path): StreamedResponse
    {
        $normalized = 'dashboard/'.ltrim(str_replace(['..', '\\'], '', $path), '/');

        if (! str_starts_with($normalized, 'dashboard/images/')) {
            abort(404);
        }

        $stream = DashboardContentStorage::readStream($normalized);
        if (! $stream) {
            abort(404);
        }

        $mimeType = DashboardContentStorage::mimeType($normalized) ?? 'application/octet-stream';

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
