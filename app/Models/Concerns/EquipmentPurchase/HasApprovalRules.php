<?php

namespace App\Models\Concerns\EquipmentPurchase;

use App\Models\AffiliationHistory;
use App\Models\User;

trait HasApprovalRules
{
    /** 社内備品・3万円以上（指定承認者） */
    public function requiresInternalOver30kApprover(): bool
    {
        return $this->application_type === self::TYPE_INTERNAL_OVER_30K;
    }

    public function requiresManagerApproval(): bool
    {
        if ($this->requiresInternalOver30kApprover()) {
            return false;
        }

        if ($this->requiresFoodDesignatedApprover()) {
            return false;
        }

        return $this->price_including_tax >= self::MANAGER_APPROVAL_MIN_AMOUNT;
    }

    /** 経理部・総務課が承認する申請か（3万円未満かつ情報システム部以外・食品指定承認以外） */
    public function requiresGeneralAffairsApproval(): bool
    {
        if ($this->belongsToInformationSystemsDepartment()) {
            return false;
        }

        if ($this->requiresFoodDesignatedApprover()) {
            return false;
        }

        return ! $this->requiresManagerApproval();
    }

    /** 食品備品の対象申請か（食品所属、または桃谷店・物流センターへの届先） */
    public function isFoodRelatedApplication(): bool
    {
        if (in_array($this->delivery_destination, [
            self::DELIVERY_FOOD_MOMOTANI,
            self::DELIVERY_FOOD_LOGISTICS,
        ], true)) {
            return true;
        }

        foreach ([$this->department, $this->section] as $name) {
            if (is_string($name) && $name !== '' && str_contains($name, self::FOOD_DEPARTMENT_KEYWORD)) {
                return true;
            }
        }

        $user = $this->relationLoaded('user') ? $this->user : $this->user()->first();
        if ($user === null) {
            return false;
        }

        $affiliation = $user->currentAffiliation();
        foreach ([$affiliation?->department, $affiliation?->section] as $name) {
            if (is_string($name) && $name !== '' && str_contains($name, self::FOOD_DEPARTMENT_KEYWORD)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 食品備品の承認ルート。
     * emergency=緊急対応(事後申請), momotani=桃谷店, logistics=物流センター
     */
    public function foodApprovalRoute(): ?string
    {
        if (! $this->isFoodRelatedApplication()) {
            return null;
        }

        if ($this->isPurchasedType()) {
            return 'emergency';
        }

        if ($this->delivery_destination === self::DELIVERY_FOOD_MOMOTANI) {
            return 'momotani';
        }

        if ($this->delivery_destination === self::DELIVERY_FOOD_LOGISTICS) {
            return 'logistics';
        }

        return null;
    }

    /** 食品備品で個人指定の承認者（総務課以外）が必要か */
    public function requiresFoodDesignatedApprover(): bool
    {
        return $this->foodDesignatedApproverEmails() !== [];
    }

    /**
     * 食品備品の指定承認者メール。
     *
     * @return list<string>
     */
    public function foodDesignatedApproverEmails(): array
    {
        $route = $this->foodApprovalRoute();
        $over30k = $this->price_including_tax >= self::MANAGER_APPROVAL_MIN_AMOUNT;

        return match ($route) {
            'emergency' => $over30k
                ? self::foodEmergencyOver30kApproverEmails()
                : self::foodEmergencyUnder30kApproverEmails(),
            'momotani' => $over30k ? self::foodMomotaniOver30kApproverEmails() : [],
            'logistics' => $over30k ? self::foodLogisticsOver30kApproverEmails() : [],
            default => [],
        };
    }

    public function belongsToDepartment(?string $department): bool
    {
        if (! $department) {
            return false;
        }

        if ($this->departmentNameMatches($this->department, $department)) {
            return true;
        }

        if ($this->departmentNameMatches($this->section, $department)) {
            return true;
        }

        return $this->user
            ->affiliationHistories()
            ->currentlyActive()
            ->get()
            ->contains(fn ($history) => $this->departmentNameMatches($history->department, $department));
    }

    public function departmentNameMatches(?string $left, ?string $right): bool
    {
        if (! $left || ! $right) {
            return false;
        }

        if ($left === $right) {
            return true;
        }

        return str_contains($left, $right) || str_contains($right, $left);
    }

    /**
     * 申請の利用先「部」「課」、未指定時は申請者の現所属に含まれる拠点キーワード（例: 福岡営業部 → 福岡）
     *
     * @return list<string>
     */
    public function officeLocationKeywords(): array
    {
        $keywords = [];
        $names = [$this->department, $this->section];

        if ($this->utilizationDepartmentUnspecified()) {
            $user = $this->relationLoaded('user') ? $this->user : $this->user()->first();
            $affiliation = $user?->currentAffiliation();
            $names[] = $affiliation?->department;
            $names[] = $affiliation?->section;
        }

        foreach ($names as $name) {
            if (! $name) {
                continue;
            }

            foreach (User::OFFICE_LOCATIONS as $location) {
                if (str_contains($name, $location)) {
                    $keywords[] = $location;
                }
            }
        }

        return array_values(array_unique($keywords));
    }

    /**
     * @param  list<string>  $keywords
     */
    public function matchesOfficeLocationKeywords(array $keywords): bool
    {
        if ($keywords === []) {
            return false;
        }

        return count(array_intersect($this->officeLocationKeywords(), $keywords)) > 0;
    }

    /** 福岡・名古屋など、支店長のみが承認する拠点の申請か */
    public function requiresBranchManagerOnlyApproval(): bool
    {
        return count(array_intersect(
            $this->officeLocationKeywords(),
            User::BRANCH_MANAGER_ONLY_OFFICE_LOCATIONS,
        )) > 0;
    }

    /** 東京など、部長→支店長の2段階承認が必要な申請か */
    public function requiresDualApproval(): bool
    {
        if (! $this->requiresManagerApproval()) {
            return false;
        }

        if ($this->requiresInternalOver30kApprover() || $this->belongsToInformationSystemsDepartment()) {
            return false;
        }

        if ($this->requiresBranchManagerOnlyApproval()) {
            return false;
        }

        return $this->isTokyoRelated()
            && $this->matchesDualApprovalDepartmentKeywords();
    }

    /** 申請が東京拠点に関係するか（利用先の部・課、または届先） */
    public function isTokyoRelated(): bool
    {
        if (in_array('東京', $this->officeLocationKeywords(), true)) {
            return true;
        }

        return in_array($this->delivery_destination, User::DUAL_APPROVAL_TOKYO_DELIVERY_DESTINATIONS, true);
    }

    /** 東京2段階承認の対象部・課か（営業部・SS課・管理部・GR部など） */
    public function matchesDualApprovalDepartmentKeywords(): bool
    {
        foreach ([$this->department, $this->section] as $name) {
            if (! $name) {
                continue;
            }

            foreach (User::DUAL_APPROVAL_TOKYO_DEPARTMENT_KEYWORDS as $keyword) {
                if (str_contains($name, $keyword)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function hasFirstApproval(): bool
    {
        return $this->first_approver_id !== null;
    }

    public function isFirstApprovalApproved(): bool
    {
        return $this->first_approval_decision === self::DECISION_APPROVED;
    }

    public function isAwaitingFirstApproval(): bool
    {
        return $this->requiresDualApproval()
            && $this->isPending()
            && ! $this->hasFirstApproval();
    }

    public function isAwaitingSecondApproval(): bool
    {
        return $this->requiresDualApproval()
            && $this->isPending()
            && $this->hasFirstApproval()
            && $this->isFirstApprovalApproved();
    }

    /** 申請が情報システム部のものかどうか（利用部署・課を優先し、未指定時のみ申請者の現所属） */
    public function belongsToInformationSystemsDepartment(): bool
    {
        if (self::nameContainsInformationSystemsKeyword($this->department)
            || self::nameContainsInformationSystemsKeyword($this->section)) {
            return true;
        }

        if (! $this->utilizationDepartmentUnspecified()) {
            return false;
        }

        return self::affiliationBelongsToInformationSystems($this->user->currentAffiliation());
    }

    public function utilizationDepartmentUnspecified(): bool
    {
        return blank($this->department) && blank($this->section);
    }

    public static function nameContainsInformationSystemsKeyword(?string $name): bool
    {
        return $name && str_contains($name, User::INFORMATION_SYSTEMS_DEPARTMENT_KEYWORD);
    }

    public static function affiliationBelongsToInformationSystems(?AffiliationHistory $affiliation): bool
    {
        if (! $affiliation) {
            return false;
        }

        return self::nameContainsInformationSystemsKeyword($affiliation->department)
            || self::nameContainsInformationSystemsKeyword($affiliation->section);
    }

    /** 上長以上（全部署横断アカウント含む）が承認する申請か */
    public function requiresSuperiorApproval(): bool
    {
        return $this->requiresInternalOver30kApprover()
            || $this->belongsToInformationSystemsDepartment()
            || $this->requiresFoodDesignatedApprover()
            || $this->requiresManagerApproval();
    }
}
