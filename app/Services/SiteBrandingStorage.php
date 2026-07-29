<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class SiteBrandingStorage
{
    public const LEGACY_DISK = 'public';

    /** @var list<string> */
    public const FILES = [
        'group-web.png',
        'care-earth-group-webpage.png',
    ];

    public static function targetDisk(): string
    {
        return (string) config('filesystems.branding_disk', self::LEGACY_DISK);
    }

    public static function sourceDirectory(): string
    {
        return resource_path('branding');
    }

    public static function path(string $filename): string
    {
        return 'branding/'.basename($filename);
    }

    public static function isAllowed(string $filename): bool
    {
        return in_array(basename($filename), self::FILES, true);
    }

    public static function url(string $filename): string
    {
        return route('branding.asset', ['path' => basename($filename)]);
    }

    public static function sourcePath(string $filename): ?string
    {
        $filename = basename($filename);
        if (! self::isAllowed($filename)) {
            return null;
        }

        $path = self::sourceDirectory().DIRECTORY_SEPARATOR.$filename;

        return is_file($path) ? $path : null;
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

    /**
     * @return list<string>
     */
    public static function candidateDisks(): array
    {
        $disks = [self::targetDisk(), self::LEGACY_DISK];

        if (config('filesystems.disks.gcs.bucket')) {
            $disks[] = 'gcs';
        }

        return array_values(array_unique($disks));
    }

    /**
     * @return array{uploaded: int, skipped: int, missing: int}
     */
    public static function syncToTargetDisk(bool $overwrite = false): array
    {
        $disk = self::targetDisk();
        $uploaded = 0;
        $skipped = 0;
        $missing = 0;

        foreach (self::FILES as $filename) {
            $source = self::sourcePath($filename);
            if ($source === null) {
                $missing++;

                continue;
            }

            $path = self::path($filename);
            if (! $overwrite && Storage::disk($disk)->exists($path)) {
                $skipped++;

                continue;
            }

            Storage::disk($disk)->put($path, file_get_contents($source));
            $uploaded++;
        }

        return compact('uploaded', 'skipped', 'missing');
    }
}
