<?php

namespace App\Services;

use App\Mail\EquipmentPurchaseApprovalRequested;
use App\Mail\EquipmentPurchaseSubmitted;
use App\Models\EquipmentPurchaseApplication;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EquipmentPurchaseApprovalNotifier
{
    public function notifySubmitted(EquipmentPurchaseApplication $application): void
    {
        $application->loadMissing('user');

        $this->sendToApplicant($application);
        $this->sendToApprovers($application);
    }

    public function notifySecondStage(EquipmentPurchaseApplication $application): void
    {
        $application->loadMissing('user');
        $this->sendToApprovers($application);
    }

    private function sendToApplicant(EquipmentPurchaseApplication $application): void
    {
        $email = $application->user?->email;

        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new EquipmentPurchaseSubmitted($application));
        } catch (\Throwable $e) {
            Log::warning('備品購入申請の受付メール送信に失敗しました。', [
                'application_id' => $application->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendToApprovers(EquipmentPurchaseApplication $application): void
    {
        $recipients = $this->resolveApprovalRecipients($application);

        if ($recipients === []) {
            Log::warning('備品購入申請の承認依頼先が見つかりませんでした。', [
                'application_id' => $application->id,
                'application_type' => $application->application_type,
                'price_including_tax' => $application->price_including_tax,
                'department' => $application->department,
                'section' => $application->section,
            ]);

            return;
        }

        foreach ($recipients as $recipient) {
            $email = $recipient['email'];
            $approver = $recipient['user'];

            if ($approver !== null && $approver->id === $application->user_id) {
                continue;
            }

            try {
                Mail::to($email)->send(
                    new EquipmentPurchaseApprovalRequested($application, $approver),
                );
            } catch (\Throwable $e) {
                Log::warning('備品購入申請の承認依頼メール送信に失敗しました。', [
                    'application_id' => $application->id,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return list<array{email: string, user: ?User}>
     */
    private function resolveApprovalRecipients(EquipmentPurchaseApplication $application): array
    {
        $recipients = [];
        $seenEmails = [];

        foreach ($this->resolveApproverUsers($application) as $user) {
            $email = strtolower(trim((string) $user->email));
            if ($email === '' || isset($seenEmails[$email])) {
                continue;
            }

            $seenEmails[$email] = true;
            $recipients[] = [
                'email' => $user->email,
                'user' => $user,
            ];
        }

        if ($application->requiresFoodDesignatedApprover()) {
            $this->appendConfiguredEmailRecipients(
                $recipients,
                $seenEmails,
                $application->foodDesignatedApproverEmails(),
            );
        } elseif ($application->requiresInternalOver30kApprover()) {
            $this->appendConfiguredEmailRecipients(
                $recipients,
                $seenEmails,
                EquipmentPurchaseApplication::internalOver30kApproverEmails(),
            );
        }

        if ($application->belongsToInformationSystemsDepartment()) {
            $this->appendConfiguredEmailRecipients(
                $recipients,
                $seenEmails,
                EquipmentPurchaseApplication::informationSystemsApproverEmails(),
            );
        }

        return $recipients;
    }

    /**
     * @param  list<array{email: string, user: ?User}>  $recipients
     * @param  array<string, true>  $seenEmails
     * @param  list<string>  $configuredEmails
     */
    private function appendConfiguredEmailRecipients(array &$recipients, array &$seenEmails, array $configuredEmails): void
    {
        foreach ($configuredEmails as $configuredEmail) {
            $normalized = strtolower(trim($configuredEmail));
            if ($normalized === '' || isset($seenEmails[$normalized])) {
                continue;
            }

            $seenEmails[$normalized] = true;
            $recipients[] = [
                'email' => $configuredEmail,
                'user' => User::query()
                    ->whereRaw('LOWER(email) = ?', [$normalized])
                    ->first(),
            ];
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveApproverUsers(EquipmentPurchaseApplication $application): Collection
    {
        return User::query()
            ->with(['affiliationHistories' => fn ($query) => $query->currentlyActive()])
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get()
            ->filter(function (User $user) use ($application) {
                if ($user->isGlobalEquipmentApprover()) {
                    // 全部署横断アカウントには通常の承認依頼メールを送らない
                    return false;
                }

                return $user->canApproveEquipmentPurchase($application);
            })
            ->unique('id')
            ->values();
    }
}
