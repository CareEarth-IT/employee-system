<?php

namespace App\Console\Commands;

use App\Mail\SocialInsuranceCompanyThresholdReached;
use App\Services\SocialInsuranceThresholdNotifier;
use App\Support\SocialInsuranceCompanyStats;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SocialInsuranceMailTestCommand extends Command
{
    protected $signature = 'social-insurance:test-mail
                            {--to=yuta_masui@careearth.info : 送信先メールアドレス}
                            {--company=CareEarth : 所属会社名（件名・本文用）}
                            {--count= : 人数（未指定時は閾値と同じ）}
                            {--notify : 本番同様に閾値到達チェックを実行（到達会社のみ送信・重複抑制あり）}';

    protected $description = '社会保険人数の閾値到達メールをテスト送信する';

    public function handle(SocialInsuranceThresholdNotifier $notifier): int
    {
        if ((bool) $this->option('notify')) {
            return $this->runNotifier($notifier);
        }

        $to = strtolower(trim((string) $this->option('to')));
        $company = trim((string) $this->option('company'));
        $threshold = SocialInsuranceCompanyStats::THRESHOLD;
        $count = $this->option('count') !== null && $this->option('count') !== ''
            ? (int) $this->option('count')
            : $threshold;

        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('有効な --to メールアドレスを指定してください。');

            return self::FAILURE;
        }

        if ($company === '') {
            $this->error('--company を指定してください。');

            return self::FAILURE;
        }

        try {
            Mail::to($to)->send(new SocialInsuranceCompanyThresholdReached(
                company: $company,
                count: $count,
                threshold: $threshold,
            ));
        } catch (\Throwable $e) {
            $this->error('送信失敗: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("テストメールを送信しました: {$to}");
        $this->line("会社: {$company} / 人数: {$count} / 閾値: {$threshold}");
        $this->line('件名例: 【CE-Group 社員専用】'.$company.' の社会保険あり人数が'.$threshold.'人に達しました');

        return self::SUCCESS;
    }

    private function runNotifier(SocialInsuranceThresholdNotifier $notifier): int
    {
        $this->info('閾値到達チェックを実行します（本番ロジック）。');

        foreach (SocialInsuranceCompanyStats::countsByCompany() as $row) {
            $mark = $row['reached'] ? '到達' : '未達';
            $this->line("  {$row['code']} {$row['company']}: {$row['count']} 人（{$mark}）");
        }

        $recipients = $notifier->recipientEmails();
        $this->line('送信先: '.($recipients === [] ? '（なし）' : implode(', ', $recipients)));

        try {
            $notifier->notifyReachedCompanies();
        } catch (\Throwable $e) {
            $this->error('送信失敗: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('完了しました。到達かつ未通知の会社があればメール送信されています。');

        return self::SUCCESS;
    }
}
