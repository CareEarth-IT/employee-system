<?php

namespace App\Console\Commands;

use App\Models\EmployeeProfile;
use App\Services\ProfilePhotoStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class MigrateProfilePhotosToGcsCommand extends Command
{
    protected $signature = 'photos:migrate-to-gcs
        {--from-disk=public : 移行元の Laravel ディスク名（さくら等の public ディスク）}
        {--from-path= : さくらから取得したローカルフォルダ（storage/app/public 相当）}
        {--delete-source : GCS へのコピー成功後に移行元ファイルを削除}
        {--dry-run : 実際にはアップロードしない}';

    protected $description = 'プロフィール写真を移行元（さくらの public ディスク等）から Cloud Storage へコピーする';

    public function handle(): int
    {
        $targetDisk = ProfilePhotoStorage::targetDisk();
        if ($targetDisk !== 'gcs') {
            $this->error('FILESYSTEM_PROFILE_PHOTOS_DISK=gcs を設定してから実行してください。');

            return self::FAILURE;
        }

        if (! config('filesystems.disks.gcs.bucket')) {
            $this->error('GCS_BUCKET が未設定です。');

            return self::FAILURE;
        }

        $fromDisk = (string) $this->option('from-disk');
        $fromPath = $this->option('from-path');
        $fromPath = is_string($fromPath) && $fromPath !== '' ? rtrim($fromPath, '/\\') : null;
        $deleteSource = (bool) $this->option('delete-source');
        $dryRun = (bool) $this->option('dry-run');

        $profiles = EmployeeProfile::query()
            ->whereNotNull('photo_path')
            ->where('photo_path', '!=', '')
            ->get();

        if ($profiles->isEmpty()) {
            $this->info('移行対象のプロフィール写真はありません。');

            return self::SUCCESS;
        }

        $this->info("対象: {$profiles->count()} 件");
        if ($dryRun) {
            $this->warn('ドライラン — アップロードは行いません。');
        }

        $migrated = 0;
        $skipped = 0;
        $missing = 0;
        $failed = 0;

        foreach ($profiles as $profile) {
            $path = $profile->photo_path;

            if (Storage::disk($targetDisk)->exists($path)) {
                $this->line("  スキップ（GCS に既存）: {$path}");
                $skipped++;

                continue;
            }

            $localPath = $fromPath !== null
                ? $fromPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path)
                : null;

            $hasSource = $localPath !== null
                ? File::isFile($localPath)
                : Storage::disk($fromDisk)->exists($path);

            if (! $hasSource) {
                $this->warn("  移行元なし: user_id={$profile->user_id} {$path}");
                $missing++;

                continue;
            }

            if ($dryRun) {
                $this->line("  移行予定: {$path}");
                $migrated++;

                continue;
            }

            try {
                if ($localPath !== null) {
                    $stream = fopen($localPath, 'rb');
                    Storage::disk($targetDisk)->writeStream($path, $stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                } else {
                    $stream = Storage::disk($fromDisk)->readStream($path);
                    Storage::disk($targetDisk)->writeStream($path, $stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                if ($deleteSource) {
                    if ($localPath !== null) {
                        File::delete($localPath);
                    } else {
                        Storage::disk($fromDisk)->delete($path);
                    }
                }

                $this->line("  移行完了: {$path}");
                $migrated++;
            } catch (\Throwable $e) {
                $this->error("  失敗: {$path} — {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("完了 — 移行: {$migrated}, スキップ: {$skipped}, 移行元なし: {$missing}, 失敗: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
