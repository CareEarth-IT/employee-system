<?php

namespace App\Services;

class DriveStaffSyncResult
{
    public function __construct(
        public bool $ok,
        public ?string $error = null,
        public bool $created = false,
    ) {}

    public static function notConfigured(): self
    {
        return new self(
            ok: false,
            error: '社用車アプリとの連携が設定されていません。管理者にお問い合わせください。',
        );
    }

    public static function invalidProfile(): self
    {
        return new self(
            ok: false,
            error: '在籍中の所属部署が登録されていないため、社用車アプリへ連携できません。プロフィールで所属を確認してください。',
        );
    }

    public static function success(bool $created = false): self
    {
        return new self(ok: true, created: $created);
    }

    public static function failure(string $error): self
    {
        return new self(ok: false, error: $error);
    }
}
