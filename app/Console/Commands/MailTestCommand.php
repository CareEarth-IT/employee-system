<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class MailTestCommand extends Command
{
    protected $signature = 'mail:test {email? : 送信先（省略時は hello@example.com）}';

    protected $description = 'ローカル向け: テストメールを1通送信する';

    public function handle(): int
    {
        $email = (string) ($this->argument('email') ?: 'hello@example.com');

        try {
            Mail::raw('CE-Group 社員専用のテストメールです。', function ($message) use ($email) {
                $message->to($email)->subject('【テスト】メール送信確認');
            });
        } catch (\Throwable $e) {
            $this->error('送信失敗: '.$e->getMessage());
            $this->line('Mailpit を起動: deploy\\mailpit.cmd');
            $this->line('.env の MAIL_MAILER=smtp, MAIL_HOST=127.0.0.1, MAIL_PORT=1025 を確認');

            return self::FAILURE;
        }

        $this->info("送信しました: {$email}");
        $this->line('Mailpit UI: http://localhost:8025');
        $this->line('MAIL_MAILER=log の場合は storage/logs/laravel.log を確認');

        return self::SUCCESS;
    }
}
