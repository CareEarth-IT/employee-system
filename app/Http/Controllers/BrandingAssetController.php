<?php

namespace App\Http\Controllers;

use App\Services\SiteBrandingStorage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BrandingAssetController extends Controller
{
    public function show(string $path): Response|BinaryFileResponse|StreamedResponse
    {
        $filename = basename($path);

        if (! SiteBrandingStorage::isAllowed($filename)) {
            abort(404);
        }

        $storagePath = SiteBrandingStorage::path($filename);
        $disk = SiteBrandingStorage::resolveDisk($storagePath);

        if ($disk !== null) {
            return Storage::disk($disk)->response($storagePath);
        }

        $source = SiteBrandingStorage::sourcePath($filename);
        if ($source !== null) {
            return response()->file($source);
        }

        abort(404);
    }
}
