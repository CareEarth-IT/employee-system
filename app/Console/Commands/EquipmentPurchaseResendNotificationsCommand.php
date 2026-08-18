<?php

namespace App\Console\Commands;

use App\Models\EquipmentPurchaseApplication;
use App\Services\EquipmentPurchaseApprovalNotifier;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EquipmentPurchaseResendNotificationsCommand extends Command
{
    protected $signature = 'equipment-purchase:resend-notifications
                            {--since=2026-07-27 : この日付以降（アプリTZ）に作成された申請を対象}
                            {--ids= : カンマ区切りの申請ID（指定時は since より優先）}
                            {--resend-approver-mail : 承認済みでも承認依頼メールを再送}
                            {--dry-run : 送信せず対象と送信種別のみ表示}';

    protected $description = '指定期間以降にメール未送信だった備品購入申請の通知を再送する';

    public function handle(EquipmentPurchaseApprovalNotifier $notifier): int
    {
        $since = Carbon::parse((string) $this->option('since'), config('app.timezone'))->startOfDay();
        $dryRun = (bool) $this->option('dry-run');
        $resendApproverMail = (bool) $this->option('resend-approver-mail');
        $idsOption = trim((string) $this->option('ids'));

        $query = EquipmentPurchaseApplication::query()
            ->with('user')
            ->orderBy('id');

        if ($idsOption !== '') {
            $ids = array_values(array_filter(array_map(
                fn (string $id) => (int) trim($id),
                preg_split('/[,;]/', $idsOption) ?: [],
            )));

            if ($ids === []) {
                $this->error('--ids に有効な申請IDを指定してください。');

                return self::FAILURE;
            }

            $query->whereIn('id', $ids);
        } else {
            $query->where('created_at', '>=', $since);
        }

        $applications = $query->get();

        if ($applications->isEmpty()) {
            $this->info("対象申請はありません（{$since->toDateString()} 以降）。");

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '対象: %d 件（%s 以降）%s',
            $applications->count(),
            $since->toDateString(),
            $dryRun ? ' [dry-run]' : '',
        ));

        $counts = [
            'submitted' => 0,
            'second_stage' => 0,
            'applicant_only' => 0,
            'approver_mail' => 0,
        ];

        foreach ($applications as $application) {
            $action = $this->resolveAction($application, $resendApproverMail);
            $applicantEmail = $application->user?->email ?? '—';

            $this->line(sprintf(
                '  #%d | %s | %s | %s | %s',
                $application->id,
                $application->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                EquipmentPurchaseApplication::TYPE_LABELS[$application->application_type] ?? $application->application_type,
                $application->status,
                $action,
            ));
            $this->line('       申請者: '.$applicantEmail);

            if ($dryRun) {
                $counts[$action]++;

                continue;
            }

            match ($action) {
                'second_stage' => $notifier->notifySecondStage($application),
                'submitted' => $notifier->notifySubmitted($application),
                'approver_mail' => $notifier->notifyApprovers($application),
                'applicant_only' => $notifier->notifyApplicantReceipt($application),
            };

            $counts[$action]++;
        }

        $this->newLine();
        $this->info(sprintf(
            '完了: 申請+承認=%d, 2次承認=%d, 承認依頼のみ=%d, 受付のみ=%d',
            $counts['submitted'],
            $counts['second_stage'],
            $counts['approver_mail'],
            $counts['applicant_only'],
        ));

        return self::SUCCESS;
    }

    /**
     * @return 'submitted'|'second_stage'|'approver_mail'|'applicant_only'
     */
    private function resolveAction(EquipmentPurchaseApplication $application, bool $resendApproverMail): string
    {
        if ($resendApproverMail) {
            return 'approver_mail';
        }

        if ($application->isPending() && $application->isAwaitingSecondApproval()) {
            return 'second_stage';
        }

        if ($application->isPending()) {
            return 'submitted';
        }

        return 'applicant_only';
    }
}
