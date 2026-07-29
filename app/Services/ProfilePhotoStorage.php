<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProfilePhotoStorage
{
    public const LEGACY_DISK = 'public';

    public static function targetDisk(): string
    {
        return EmployeeProfile::photoDisk();
    }

    public static function store(UploadedFile $file): string
    {
        return $file->store('photos', self::targetDisk());
    }

    public static function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        foreach (self::candidateDisks() as $disk) {
            $storage = Storage::disk($disk);
            if ($storage->exists($path)) {
                $storage->delete($path);
            }
        }
    }

    public static function resolveDisk(string $path): ?string
    {
        foreach (self::candidateDisks() as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    public static function exists(string $path): bool
    {
        return self::resolveDisk($path) !== null;
    }

    /**
     * @return resource|null
     */
    public static function readStream(string $path)
    {
        $disk = self::resolveDisk($path);

        return $disk ? Storage::disk($disk)->readStream($path) : null;
    }

    public static function mimeType(string $path): ?string
    {
        $disk = self::resolveDisk($path);

        return $disk ? Storage::disk($disk)->mimeType($path) : null;
    }

    /**
     * @return list<string>
     */
    public static function candidateDisks(): array
    {
        $target = self::targetDisk();
        $legacy = self::LEGACY_DISK;

        if ($target === $legacy) {
            return [$target];
        }

        return [$target, $legacy];
    }
}
