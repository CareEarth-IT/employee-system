<?php

namespace App\Console\Commands;

use App\Mail\EquipmentPurchaseApprovalRequested;
use App\Mail\EquipmentPurchaseSubmitted;
use App\Models\EquipmentPurchaseApplication;
use App\Models\User;
use App\Services\EquipmentPurchaseApprovalNotifier;
use App\Services\EquipmentPurchaseSubmissionPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class EquipmentPurchaseMailTestCommand extends Command
{
    protected $signature = 'equipment-purchase:test-mail
                            {--type=internal_over_30k : 申請タイプ (internal_over_30k, onsite_over_30k, internal_under_30k など)}
                            {--applicant=yuta_masui@careearth.info : 申請者メール}
                            {--price= : 価格（税込）。未指定時はタイプに合わせる}
                            {--department=通信部 : 利用先の部（現場用3万円以上の部長承認テスト用）}
                            {--check-only : 承認先の確認のみ（申請作成・メール送信なし）}
                            {--self-only : 受付・承認依頼を --applicant のアドレスにのみ送信（本番承認者には送らない）}';

    protected $description = '備品購入申請の受付・承認依頼メールをテスト送信する（DBにテスト申請を1件作成）';

    public function handle(EquipmentPurchaseApprovalNotifier $notifier): int
    {
        $type = (string) $this->option('type');
        if (! array_key_exists($type, EquipmentPurchaseApplication::TYPE_LABELS)) {
            $this->error("不明な申請タイプ: {$type}");
            $this->line('例: internal_over_30k, onsite_over_30k, internal_under_30k');

            return self::FAILURE;
        }

        $applicantEmail = strtolower(trim((string) $this->option('applicant')));
        $applicant = User::query()->whereRaw('LOWER(email) = ?', [$applicantEmail])->first();

        if ($applicant === null) {
            $this->error("申請者が見つかりません: {$applicantEmail}");
            $this->line('社員マスタに登録されているメールアドレスを --applicant= に指定してください。');

            return self::FAILURE;
        }

        $price = $this->resolvePrice($type, $this->option('price'));
        if (! EquipmentPurchaseApplication::priceMatchesApplicationType($type, $price)) {
            $this->error(EquipmentPurchaseApplication::priceValidationMessageForType($type) ?? '価格と申請タイプが一致しません。');

            return self::FAILURE;
        }

        $department = trim((string) $this->option('department'));
        $checkOnly = (bool) $this->option('check-only');
        $selfOnly = (bool) $this->option('self-only');

        if ($checkOnly) {
            $application = new EquipmentPurchaseApplication([
                'user_id' => $applicant->id,
                'application_type' => $type,
                'price_including_tax' => $price,
                'department' => $department !== '' ? $department : $applicant->currentDepartment(),
                'section' => null,
                'status' => EquipmentPurchaseApplication::STATUS_PENDING,
            ]);
            $application->setRelation('user', $applicant);

            $this->info('承認先確認モード（申請作成・メール送信なし）');
            $this->line('申請タイプ: '.EquipmentPurchaseApplication::TYPE_LABELS[$type]);
            $this->line('申請者: '.$applicant->displayName().' <'.$applicant->email.'>');
            $this->line('利用先の部: '.$application->department);
            $this->line('価格（税込）: '.number_format($price).' 円');
            $this->line('拠点キーワード: '.implode(', ', $application->officeLocationKeywords()) ?: '（なし）');
            $this->line('支店長のみ承認: '.($application->requiresBranchManagerOnlyApproval() ? 'はい' : 'いいえ'));
            $this->line('2段階承認: '.($application->requiresDualApproval() ? 'はい（部長→支店長）' : 'いいえ'));
            if ($application->isTokyoRelated()) {
                $this->line('東京関連: はい');
                $this->line('対象部門キーワード一致: '.($application->matchesDualApprovalDepartmentKeywords() ? 'はい' : 'いいえ'));
            }

            $this->printBranchManagerDiagnostics($application);

            if ($selfOnly) {
                $this->newLine();
                $this->info('送信先（--self-only）:');
                $this->line('  受付メール → '.$applicant->email);
                $this->line('  承認依頼 → '.$applicant->email);
                $this->newLine();
                $this->comment('※ --check-only のためメールは送信しません。');
                $this->comment('  送信する場合: --self-only を付けて --check-only を外してください。');
            } else {
                $recipientEmails = $this->resolveRecipientEmails($application);
                $this->newLine();
                $this->info('承認依頼の送信先（本番と同じ）:');
                if ($recipientEmails === []) {
                    $this->warn('  （承認者が見つかりません）');
                } else {
                    foreach ($recipientEmails as $email) {
                        $this->line('  → '.$email);
                    }
                }
                $this->newLine();
                $this->comment('※ --check-only のためメールは送信しません。');
                $this->comment('  自分だけに送る場合: --self-only を付けて --check-only を外してください。');
            }

            return self::SUCCESS;
        }

        $application = $applicant->equipmentPurchaseApplications()->create([
            'application_type' => $type,
            'purchase_site' => 'Amazon',
            'purchase_site_url' => 'https://example.test/equipment-mail-test',
            'product_name' => '【メールテスト】備品購入申請',
            'quantity' => 1,
            'price_including_tax' => $price,
            'purchase_reason' => 'メール送信テスト（equipment-purchase:test-mail）',
            'item_destination' => EquipmentPurchaseApplication::DESTINATION_DEPARTMENT_ALL,
            'department' => $department !== '' ? $department : $applicant->currentDepartment(),
            'section' => null,
            'delivery_destination' => 'osaka_2f',
            'purchase_urgency' => EquipmentPurchaseApplication::URGENCY_NO_RUSH,
            'application_date' => EquipmentPurchaseSubmissionPeriod::resolveApplicationDate(),
            'status' => EquipmentPurchaseApplication::STATUS_PENDING,
        ]);

        $this->info('テスト申請を作成しました (ID: '.$application->id.')');
        $this->line('申請タイプ: '.EquipmentPurchaseApplication::TYPE_LABELS[$type]);
        $this->line('申請者: '.$applicant->displayName().' <'.$applicant->email.'>');
        $this->line('価格（税込）: '.number_format($price).' 円');

        $this->newLine();
        $this->info('送信予定:');
        if ($selfOnly) {
            $this->line('  受付メール → '.$applicant->email);
            $this->line('  承認依頼 → '.$applicant->email.' （--self-only）');
        } else {
            $recipientEmails = $this->resolveRecipientEmails($application);
            $this->line('  受付メール → '.$applicant->email);
            if ($recipientEmails === []) {
                $this->warn('  承認依頼 → （承認者が見つかりません）');
            } else {
                foreach ($recipientEmails as $email) {
                    $this->line('  承認依頼 → '.$email);
                }
            }
        }

        try {
            if ($selfOnly) {
                $this->sendSelfOnly($application, $applicant);
            } else {
                $notifier->notifySubmitted($application);
            }
        } catch (\Throwable $e) {
            $this->error('送信失敗: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('メール送信を実行しました。受信トレイ（迷惑メールも）を確認してください。');
        $this->line('件名例:');
        $this->line('  【CE-Group 社員専用】備品購入申請を受け付けました');
        $this->line('  【CE-Group 社員専用】備品購入申請の承認をお願いします');

        return self::SUCCESS;
    }

    private function printBranchManagerDiagnostics(EquipmentPurchaseApplication $application): void
    {
        $branchManagers = User::query()
            ->with(['affiliationHistories' => fn ($query) => $query->currentlyActive()])
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get()
            ->filter(fn (User $user) => $user->isBranchManager());

        $this->newLine();
        $this->info('支店長一覧（在籍中）: '.$branchManagers->count().' 名');

        foreach ($branchManagers as $user) {
            $affiliation = $user->currentAffiliation();
            $canApprove = $user->canApproveEquipmentPurchase($application);
            $this->line(sprintf(
                '  %s | %s | %s | 拠点: %s | 承認対象: %s',
                $user->email,
                $affiliation?->department ?? '—',
                $affiliation?->position ?? '—',
                implode(',', $user->branchOfficeKeywords()) ?: '—',
                $canApprove ? 'はい' : 'いいえ',
            ));
        }
    }

    private function resolvePrice(string $type, mixed $priceOption): int
    {
        if (is_numeric($priceOption)) {
            return (int) $priceOption;
        }

        return in_array($type, EquipmentPurchaseApplication::OVER_30K_TYPES, true) ? 35000 : 12000;
    }

    /**
     * @return list<string>
     */
    private function resolveRecipientEmails(EquipmentPurchaseApplication $application): array
    {
        $emails = [];

        foreach (User::query()
            ->with(['affiliationHistories' => fn ($query) => $query->currentlyActive()])
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get()
            ->filter(fn (User $user) => $user->canApproveEquipmentPurchase($application)) as $user) {
            $email = strtolower(trim((string) $user->email));
            if ($email !== '' && $user->id !== $application->user_id) {
                $emails[$email] = $user->email;
            }
        }

        if ($application->requiresInternalOver30kApprover()) {
            foreach (EquipmentPurchaseApplication::internalOver30kApproverEmails() as $configuredEmail) {
                $normalized = strtolower(trim($configuredEmail));
                if ($normalized !== '' && $normalized !== strtolower(trim((string) $application->user?->email))) {
                    $emails[$normalized] = $configuredEmail;
                }
            }
        }

        return array_values($emails);
    }

    private function sendSelfOnly(EquipmentPurchaseApplication $application, User $applicant): void
    {
        $application->loadMissing('user');

        Mail::to($applicant->email)->send(new EquipmentPurchaseSubmitted($application));
        Mail::to($applicant->email)->send(
            new EquipmentPurchaseApprovalRequested($application, $applicant),
        );
    }
}
