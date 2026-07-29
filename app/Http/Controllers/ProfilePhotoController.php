<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ProfilePhotoStorage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ProfilePhotoController extends Controller
{
    public function show(User $user): Response
    {
        $profile = $user->profile;

        if (! $profile?->photo_path) {
            abort(404);
        }

        $disk = ProfilePhotoStorage::resolveDisk($profile->photo_path);

        if ($disk === null) {
            abort(404);
        }

        return Storage::disk($disk)->response($profile->photo_path);
    }
}
