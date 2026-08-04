<?php

namespace App\Models\Concerns\EquipmentPurchase;

use App\Models\User;
use App\Services\EquipmentPurchaseSubmissionPeriod;
use App\Support\RequestUrl;
use Carbon\Carbon;

trait HasPresentation
{
    public function purchaseSiteLabel(): string
    {
        if ($this->purchase_site === 'その他' && $this->purchase_site_other) {
            return $this->purchase_site_other;
        }

        return $this->purchase_site;
    }

    public function itemDestinationLabel(): string
    {
        $label = self::ITEM_DESTINATIONS[$this->item_destination] ?? $this->item_destination;

        if ($this->item_destination === self::DESTINATION_DEPARTMENT_ALL && $this->department) {
            return "{$label}（{$this->department}）";
        }

        if ($this->item_destination === self::DESTINATION_SECTION_ONLY) {
            $detail = trim(implode(' / ', array_filter([$this->department, $this->section])));

            return $detail ? "{$label}（{$detail}）" : $label;
        }

        if ($this->item_destination === self::DESTINATION_ONSITE && $this->onsite_name) {
            return "{$label}（{$this->onsite_name}）";
        }

        return $label;
    }

    public function deliveryDestinationLabel(): string
    {
        $label = self::DELIVERY_DESTINATIONS[$this->delivery_destination] ?? $this->delivery_destination;

        if ($this->delivery_destination === self::DELIVERY_OTHER) {
            $parts = array_filter([
                $this->delivery_zip ? "〒{$this->delivery_zip}" : null,
                $this->delivery_address,
                $this->delivery_recipient_name,
                $this->delivery_recipient_phone,
            ]);

            return $parts ? "{$label}（".implode(' / ', $parts).'）' : $label;
        }

        return $label;
    }

    public function purchaseUrgencyLabel(): string
    {
        return self::PURCHASE_URGENCIES[$this->purchase_urgency] ?? $this->purchase_urgency;
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->application_type] ?? $this->application_type;
    }

    public function applicationMonthLabel(): string
    {
        $reference = $this->created_at ?? $this->application_date;

        return EquipmentPurchaseSubmissionPeriod::submissionTargetMonthLabel(
            $reference instanceof Carbon ? $reference : Carbon::parse($reference),
        );
    }

    public function applicationDateDisplay(): string
    {
        return $this->application_date->format('Y/m/d');
    }

    public static function isPurchasedApplicationType(?string $type): bool
    {
        return in_array($type, [
            self::TYPE_PURCHASED_UNDER_10K,
            self::TYPE_PURCHASED_OVER_10K,
        ], true);
    }

    public function isPurchasedType(): bool
    {
        return self::isPurchasedApplicationType($this->application_type);
    }

    public function isInternalType(): bool
    {
        return in_array($this->application_type, [
            self::TYPE_INTERNAL_UNDER_30K,
            self::TYPE_INTERNAL_OVER_30K,
        ], true);
    }

    public function listDepartmentLabel(): string
    {
        return match ($this->item_destination) {
            self::DESTINATION_SECTION_ONLY => trim(implode(' / ', array_filter([$this->department, $this->section])))
                ?: (self::ITEM_DESTINATIONS[$this->item_destination] ?? '—'),
            self::DESTINATION_DEPARTMENT_ALL => $this->department ?: '部全体用',
            self::DESTINATION_LOCATION_ALL => '拠点全体',
            self::DESTINATION_CE_GROUP_ALL => 'CE-Group 全体',
            self::DESTINATION_ONSITE => $this->onsite_name
                ? '現場（'.$this->onsite_name.'）'
                : '現場',
            default => '—',
        };
    }

    public function listMemo(): string
    {
        return $this->approval_memo ?: ($this->remarks ?? '');
    }

    public function approvalStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => '承認済',
            self::STATUS_REJECTED => '否',
            default => '未',
        };
    }

    public function approvalDecisionLabel(): string
    {
        if ($this->approval_decision === null) {
            return '';
        }

        return self::APPROVAL_DECISIONS[$this->approval_decision] ?? $this->approval_decision;
    }

    public function firstApprovalDecisionLabel(): string
    {
        if ($this->first_approval_decision === null) {
            return '';
        }

        return self::APPROVAL_DECISIONS[$this->first_approval_decision] ?? $this->first_approval_decision;
    }

    public function approvalStatusColorClass(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'text-green-600',
            self::STATUS_REJECTED => 'text-red-600',
            default => 'text-blue-600',
        };
    }

    public function approvalStatusIcon(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => '✓ ',
            self::STATUS_REJECTED => '✗ ',
            default => '',
        };
    }

    public function approvalSummaryText(): string
    {
        if ($this->status === self::STATUS_REJECTED) {
            if ($this->hasFirstApproval() && $this->first_approval_decision === self::DECISION_DENIED) {
                return '✗ 否（1次承認：'.$this->firstApprovalDecisionLabel().'）';
            }

            return '✗ 否（'.$this->approvalDecisionLabel().'）';
        }

        if ($this->status === self::STATUS_APPROVED) {
            if ($this->requiresDualApproval()) {
                return '✓ 承認済（1次・2次とも許可）';
            }

            return '✓ 承認済（'.$this->approvalDecisionLabel().'）';
        }

        if ($this->isAwaitingSecondApproval()) {
            return '1次承認済・支店長承認待ち';
        }

        if ($this->requiresDualApproval()) {
            return '部長承認待ち';
        }

        return '未';
    }

    public function detailUrl(?User $viewer = null): string
    {
        $viewer ??= auth()->user();

        if ($this->isPending() && $viewer->canApproveEquipmentPurchase($this)) {
            return RequestUrl::route('equipment-purchases.approve', $this);
        }

        if ($viewer->canViewEquipmentPurchaseApplication($this)) {
            return RequestUrl::route('equipment-purchases.show', $this);
        }

        return RequestUrl::route('equipment-purchases.list');
    }

    public function approvedAtDisplay(): ?string
    {
        return $this->approved_at?->timezone(config('app.timezone'))->format('Y/m/d H:i');
    }

    public function firstApprovedAtDisplay(): ?string
    {
        return $this->first_approved_at?->timezone(config('app.timezone'))->format('Y/m/d H:i');
    }

    public function orderDateDisplay(): ?string
    {
        return $this->order_date?->format('Y/m/d');
    }

    public function arrivalDateDisplay(): ?string
    {
        return $this->arrival_date?->format('Y/m/d');
    }

    public function receiptIssuedLabel(): string
    {
        return $this->receipt_issued ? '発行済' : '未発行';
    }
}
