<?php

namespace App\Services;

class EmployeeBulkImportResult
{
    /**
     * @param  list<list<string>>  $rows
     * @param  list<string>  $errors
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $errors,
        public readonly int $created,
        public readonly int $skippedExisting,
        public readonly int $updated,
        public readonly bool $dryRun,
    ) {}

    public function failed(): bool
    {
        return $this->errors !== [];
    }

    public function summaryMessage(): string
    {
        if ($this->dryRun) {
            return 'dry-run のため DB は変更していません。';
        }

        $parts = [];
        if ($this->created > 0) {
            $parts[] = "新規登録 {$this->created} 名";
        }
        if ($this->skippedExisting > 0) {
            $parts[] = "既存のため変更なし {$this->skippedExisting} 名";
        }
        if ($this->updated > 0) {
            $parts[] = "更新 {$this->updated} 名";
        }

        if ($parts === []) {
            return '処理対象の行がありませんでした。';
        }

        return '一括登録が完了しました（'.implode('、', $parts).'）。既存社員のデータは変更していません。初回ログインはパスワード再設定画面からパスワードを設定してください。';
    }
}
