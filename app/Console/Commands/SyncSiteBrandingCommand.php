<?php

namespace App\Console\Commands;

use App\Services\SiteBrandingStorage;
use Illuminate\Console\Command;

class SyncSiteBrandingCommand extends Command
{
    protected $signature = 'branding:sync
        {--overwrite : 既存ファイルも上書きする}';

    protected $description = 'ログイン等のブランド画像をストレージ（本番は Cloud Storage）へ同期する';

    public function handle(): int
    {
        $disk = SiteBrandingStorage::targetDisk();
        $this->info("対象ディスク: {$disk}");

        if ($disk === 'gcs' && ! config('filesystems.disks.gcs.bucket')) {
            $this->error('GCS_BUCKET が未設定です。');

            return self::FAILURE;
        }

        $result = SiteBrandingStorage::syncToTargetDisk((bool) $this->option('overwrite'));

        $this->info("完了 — アップロード: {$result['uploaded']}, スキップ: {$result['skipped']}, ソースなし: {$result['missing']}");

        return $result['missing'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
