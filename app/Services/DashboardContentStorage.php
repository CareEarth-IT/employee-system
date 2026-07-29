<?php

namespace App\Services;

use App\Support\DashboardTab;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DashboardContentStorage
{
    public const LEGACY_DISK = 'public';

    public static function targetDisk(): string
    {
        return (string) config('filesystems.dashboard_contents_disk', self::LEGACY_DISK);
    }

    public static function storeHtml(string $department, string $html, ?int $contentId = null): string
    {
        $slug = self::departmentSlug($department);
        $filename = $contentId ? "{$slug}/{$contentId}.html" : "{$slug}.html";
        $path = 'dashboard/contents/'.$filename;
        Storage::disk(self::targetDisk())->put($path, $html);

        return $path;
    }

    public static function storeImage(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';

        $path = $file->storeAs(
            'dashboard/images',
            Str::uuid().'.'.strtolower($extension),
            self::targetDisk(),
        );

        if (! is_string($path) || $path === '') {
            throw new \RuntimeException('画像の保存に失敗しました。ストレージの設定を確認してください。');
        }

        return $path;
    }

    public static function assetUrl(string $path): string
    {
        return route('dashboard-contents.asset', [
            'path' => ltrim(str_replace('dashboard/', '', $path), '/'),
        ]);
    }

    public static function read(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $disk = self::resolveDisk($path);

        return $disk ? Storage::disk($disk)->get($path) : null;
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

    public static function departmentSlug(string $department): string
    {
        $tab = DashboardTab::findByDepartment($department);

        if ($tab !== null) {
            return $tab['key'];
        }

        $slug = Str::slug($department, '_');

        return $slug !== '' ? $slug : hash('crc32b', $department);
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
