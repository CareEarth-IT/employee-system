<?php

namespace App\Models\Concerns\EquipmentPurchase;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait HasApprovalScopes
{
    /**
     * 東京拠点に関係する申請（利用先の部・課、または届先）
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeMatchingTokyoContext($query)
    {
        return $query->where(function ($outer) {
            $outer->where(function ($inner) {
                $inner->where('department', 'like', '%東京%')
                    ->orWhere('section', 'like', '%東京%');
            })->orWhereIn('delivery_destination', User::DUAL_APPROVAL_TOKYO_DELIVERY_DESTINATIONS);
        });
    }

    /**
     * 東京2段階承認の対象部・課に一致する申請
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeMatchingDualApprovalDepartmentKeywords($query)
    {
        return $query->where(function ($outer) {
            foreach (User::DUAL_APPROVAL_TOKYO_DEPARTMENT_KEYWORDS as $keyword) {
                $outer->orWhere('department', 'like', '%'.$keyword.'%')
                    ->orWhere('section', 'like', '%'.$keyword.'%');
            }
        });
    }

    /**
     * 2段階承認が必要な申請
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRequiringDualApproval($query)
    {
        return $query
            ->matchingTokyoContext()
            ->matchingDualApprovalDepartmentKeywords();
    }

    /**
     * 2段階承認が不要な申請
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeNotRequiringDualApproval($query)
    {
        return $query->where(function ($outer) {
            $outer->whereNot(fn ($inner) => $inner->matchingTokyoContext())
                ->orWhereNot(fn ($inner) => $inner->matchingDualApprovalDepartmentKeywords());
        });
    }

    /**
     * 支店長のみ承認の拠点を除く（部長向け一覧用）
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeExcludingBranchManagerOnlyLocations($query)
    {
        return $query->where(function ($outer) {
            foreach (User::BRANCH_MANAGER_ONLY_OFFICE_LOCATIONS as $location) {
                $outer->where(function ($inner) use ($location) {
                    $inner->where(function ($department) use ($location) {
                        $department->whereNull('department')
                            ->orWhere('department', 'not like', '%'.$location.'%');
                    })->where(function ($section) use ($location) {
                        $section->whereNull('section')
                            ->orWhere('section', 'not like', '%'.$location.'%');
                    });
                });
            }
        });
    }

    /**
     * 経理部・総務課向け: 3万円未満かつ情報システム部以外の承認待ち申請
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApprovableByGeneralAffairs($query)
    {
        $foodKeyword = '%'.self::FOOD_DEPARTMENT_KEYWORD.'%';
        $purchasedTypes = [
            self::TYPE_PURCHASED_UNDER_10K,
            self::TYPE_PURCHASED_OVER_10K,
        ];

        return $query
            ->where('price_including_tax', '<', self::MANAGER_APPROVAL_MIN_AMOUNT)
            ->notBelongingToInformationSystemsDepartment()
            // 食品・緊急対応（事後申請）は総務課ではなく指定承認者
            ->where(function ($inner) use ($purchasedTypes, $foodKeyword) {
                $inner->whereNotIn('application_type', $purchasedTypes)
                    ->orWhere(fn ($notFoodEmergency) => $notFoodEmergency->whereNotMatchingFoodRelatedMarkers($foodKeyword));
            });
    }

    /**
     * 食品備品の指定承認者向け承認待ち
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApprovableByFoodDesignatedApprover($query, User $approver)
    {
        $email = strtolower(trim((string) $approver->email));
        if ($email === '') {
            return $query->whereRaw('0 = 1');
        }

        $foodKeyword = '%'.self::FOOD_DEPARTMENT_KEYWORD.'%';
        $purchasedTypes = [
            self::TYPE_PURCHASED_UNDER_10K,
            self::TYPE_PURCHASED_OVER_10K,
        ];

        return $query->where(function ($outer) use ($email, $foodKeyword, $purchasedTypes) {
            if (in_array($email, self::foodEmergencyUnder30kApproverEmails(), true)) {
                $outer->orWhere(function ($q) use ($purchasedTypes, $foodKeyword) {
                    $q->whereIn('application_type', $purchasedTypes)
                        ->where('price_including_tax', '<', self::MANAGER_APPROVAL_MIN_AMOUNT)
                        ->where(fn ($food) => $food->matchingFoodRelatedMarkers($foodKeyword));
                });
            }

            if (in_array($email, self::foodEmergencyOver30kApproverEmails(), true)) {
                $outer->orWhere(function ($q) use ($purchasedTypes, $foodKeyword) {
                    $q->whereIn('application_type', $purchasedTypes)
                        ->where('price_including_tax', '>=', self::MANAGER_APPROVAL_MIN_AMOUNT)
                        ->where(fn ($food) => $food->matchingFoodRelatedMarkers($foodKeyword));
                });
            }

            if (in_array($email, self::foodMomotaniOver30kApproverEmails(), true)) {
                $outer->orWhere(function ($q) use ($purchasedTypes) {
                    $q->whereNotIn('application_type', $purchasedTypes)
                        ->where('delivery_destination', self::DELIVERY_FOOD_MOMOTANI)
                        ->where('price_including_tax', '>=', self::MANAGER_APPROVAL_MIN_AMOUNT);
                });
            }

            if (in_array($email, self::foodLogisticsOver30kApproverEmails(), true)) {
                $outer->orWhere(function ($q) use ($purchasedTypes) {
                    $q->whereNotIn('application_type', $purchasedTypes)
                        ->where('delivery_destination', self::DELIVERY_FOOD_LOGISTICS)
                        ->where('price_including_tax', '>=', self::MANAGER_APPROVAL_MIN_AMOUNT);
                });
            }
        });
    }

    /**
     * 食品備品判定（申請の部・課・届先、または申請者の現所属）— isFoodRelatedApplication() と揃える
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeMatchingFoodRelatedMarkers($query, string $foodKeyword)
    {
        return $query->where(function ($food) use ($foodKeyword) {
            $food->where('department', 'like', $foodKeyword)
                ->orWhere('section', 'like', $foodKeyword)
                ->orWhereIn('delivery_destination', [
                    self::DELIVERY_FOOD_MOMOTANI,
                    self::DELIVERY_FOOD_LOGISTICS,
                ])
                ->orWhereHas('user.affiliationHistories', function ($affiliation) use ($foodKeyword) {
                    $affiliation->currentlyActive()->where(function ($match) use ($foodKeyword) {
                        $match->where('department', 'like', $foodKeyword)
                            ->orWhere('section', 'like', $foodKeyword);
                    });
                });
        });
    }

    /**
     * 食品関連マーカーに一致しない申請（緊急対応除外の共通条件）
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWhereNotMatchingFoodRelatedMarkers($query, string $foodKeyword)
    {
        return $query
            ->where(function ($delivery) {
                $delivery->whereNull('delivery_destination')
                    ->orWhereNotIn('delivery_destination', [
                        self::DELIVERY_FOOD_MOMOTANI,
                        self::DELIVERY_FOOD_LOGISTICS,
                    ]);
            })
            ->where(function ($dept) use ($foodKeyword) {
                $dept->where(function ($blank) {
                    $blank->whereNull('department')->orWhere('department', '');
                })->orWhere('department', 'not like', $foodKeyword);
            })
            ->where(function ($sect) use ($foodKeyword) {
                $sect->where(function ($blank) {
                    $blank->whereNull('section')->orWhere('section', '');
                })->orWhere('section', 'not like', $foodKeyword);
            })
            ->whereDoesntHave('user.affiliationHistories', function ($affiliation) use ($foodKeyword) {
                $affiliation->currentlyActive()->where(function ($match) use ($foodKeyword) {
                    $match->where('department', 'like', $foodKeyword)
                        ->orWhere('section', 'like', $foodKeyword);
                });
            });
    }

    /**
     * 情報システム部の申請
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeBelongingToInformationSystemsDepartment($query)
    {
        $keyword = User::INFORMATION_SYSTEMS_DEPARTMENT_KEYWORD;
        $like = '%'.$keyword.'%';
        $today = now()->toDateString();

        return $query->where(function ($inner) use ($like, $today) {
            $inner->where(function ($specified) use ($like) {
                $specified->where(function ($hasUtilization) {
                    $hasUtilization->whereNotNull('department')->where('department', '!=', '')
                        ->orWhereNotNull('section')->where('section', '!=', '');
                })->where(function ($matches) use ($like) {
                    $matches->where(function ($department) use ($like) {
                        $department->whereNotNull('department')
                            ->where('department', '!=', '')
                            ->where('department', 'like', $like);
                    })->orWhere(function ($section) use ($like) {
                        $section->whereNotNull('section')
                            ->where('section', '!=', '')
                            ->where('section', 'like', $like);
                    });
                });
            })->orWhere(function ($unspecified) use ($like, $today) {
                $unspecified->where(function ($blank) {
                    $blank->where(function ($q) {
                        $q->whereNull('department')->orWhere('department', '=', '');
                    })->where(function ($q) {
                        $q->whereNull('section')->orWhere('section', '=', '');
                    });
                })->whereExists(function ($exists) use ($like, $today) {
                    $exists->selectRaw('1')
                        ->from('affiliation_histories as ah')
                        ->whereColumn('ah.user_id', 'equipment_purchase_applications.user_id')
                        ->where(function ($active) use ($today) {
                            $active->whereNull('ah.end_date')
                                ->orWhere('ah.end_date', '>=', $today);
                        })
                        ->where(function ($match) use ($like) {
                            $match->where(function ($department) use ($like) {
                                $department->whereNotNull('ah.department')
                                    ->where('ah.department', 'like', $like);
                            })->orWhere(function ($section) use ($like) {
                                $section->whereNotNull('ah.section')
                                    ->where('ah.section', 'like', $like);
                            });
                        })
                        ->whereNotExists(function ($newer) use ($today) {
                            $newer->selectRaw('1')
                                ->from('affiliation_histories as ah2')
                                ->whereColumn('ah2.user_id', 'ah.user_id')
                                ->where(function ($active) use ($today) {
                                    $active->whereNull('ah2.end_date')
                                        ->orWhere('ah2.end_date', '>=', $today);
                                })
                                ->whereColumn('ah2.start_date', '>', 'ah.start_date');
                        });
                });
            });
        });
    }

    /**
     * 情報システム部以外の申請
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeNotBelongingToInformationSystemsDepartment($query)
    {
        return $query->whereNot(function ($inner) {
            $inner->belongingToInformationSystemsDepartment();
        });
    }

    /**
     * 情報システム部指定承認者向け: 情報システム部の承認待ち申請（金額問わず）
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApprovableByInformationSystemsRepresentative($query)
    {
        return $query->belongingToInformationSystemsDepartment();
    }

    /**
     * 全部署・上長以上承認者向け: 経理総務の3万円未満以外の承認待ち申請
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApprovableByGlobalManagerApprover($query)
    {
        return $query->where(function ($inner) {
            $inner->where('application_type', self::TYPE_INTERNAL_OVER_30K)
                ->orWhere(fn ($sub) => $sub->belongingToInformationSystemsDepartment())
                ->orWhere(function ($manager) {
                    $manager->where('price_including_tax', '>=', self::MANAGER_APPROVAL_MIN_AMOUNT)
                        ->where('application_type', '!=', self::TYPE_INTERNAL_OVER_30K);
                });
        });
    }

    /**
     * 部長の所属「部」と一致する申請（部分一致）
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeMatchingManagerDepartment($query, ?string $department)
    {
        if (! $department) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function ($inner) use ($department) {
            $inner->where(function ($departmentMatch) use ($department) {
                $departmentMatch->where('department', $department)
                    ->orWhere('department', 'like', '%'.$department.'%')
                    ->orWhereRaw('? LIKE CONCAT("%", department, "%")', [$department]);
            })->orWhere(function ($sectionMatch) use ($department) {
                // belongsToDepartment() と同様、利用先の「課」でも部長部署と一致させる
                $sectionMatch->whereNotNull('section')
                    ->where('section', '!=', '')
                    ->where(function ($match) use ($department) {
                        $match->where('section', $department)
                            ->orWhere('section', 'like', '%'.$department.'%')
                            ->orWhereRaw('? LIKE CONCAT("%", section, "%")', [$department]);
                    });
            })->orWhereHas('user.affiliationHistories', function ($affiliation) use ($department) {
                $affiliation->currentlyActive()->where(function ($match) use ($department) {
                    $match->where('department', $department)
                        ->orWhere('department', 'like', '%'.$department.'%')
                        ->orWhereRaw('? LIKE CONCAT("%", department, "%")', [$department]);
                });
            });
        });
    }

    public function scopeApprovableByInternalOver30kApprover($query)
    {
        return $query->where('application_type', self::TYPE_INTERNAL_OVER_30K);
    }

    /**
     * 支店長の拠点キーワードと一致する申請（例: 福岡営業部 → 福岡）
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeMatchingOfficeLocationKeywords($query, array $keywords)
    {
        if ($keywords === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function ($inner) use ($keywords) {
            foreach ($keywords as $keyword) {
                $like = '%'.$keyword.'%';
                $inner->orWhere('department', 'like', $like)
                    ->orWhere('section', 'like', $like)
                    ->orWhere(function ($unspecified) use ($like) {
                        // 利用先の部・課が空のときは申請者所属で拠点判定（officeLocationKeywords と揃える）
                        $unspecified
                            ->where(function ($department) {
                                $department->whereNull('department')->orWhere('department', '');
                            })
                            ->where(function ($section) {
                                $section->whereNull('section')->orWhere('section', '');
                            })
                            ->whereHas('user.affiliationHistories', function ($affiliation) use ($like) {
                                $affiliation->currentlyActive()->where(function ($match) use ($like) {
                                    $match->where('department', 'like', $like)
                                        ->orWhere('section', 'like', $like);
                                });
                            });
                    });
            }
        });
    }

    /**
     * 支店長向け: 3万円以上かつ拠点名が一致（情報システム部以外、社内3万円以上は除く）
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApprovableByBranchManager($query, User $branchManager)
    {
        $keywords = $branchManager->branchOfficeKeywords();
        $branchOnlyKeywords = array_values(array_intersect(
            $keywords,
            User::BRANCH_MANAGER_ONLY_OFFICE_LOCATIONS,
        ));
        $dualKeywords = array_values(array_intersect(
            $keywords,
            User::DUAL_APPROVAL_OFFICE_LOCATIONS,
        ));
        $otherKeywords = array_values(array_diff(
            $keywords,
            User::BRANCH_MANAGER_ONLY_OFFICE_LOCATIONS,
            User::DUAL_APPROVAL_OFFICE_LOCATIONS,
        ));

        return $query
            ->where('application_type', '!=', self::TYPE_INTERNAL_OVER_30K)
            ->where('price_including_tax', '>=', self::MANAGER_APPROVAL_MIN_AMOUNT)
            ->notBelongingToInformationSystemsDepartment()
            ->excludingFoodDesignatedApprovals()
            ->where(function ($inner) use ($branchOnlyKeywords, $dualKeywords, $otherKeywords) {
                if ($branchOnlyKeywords !== []) {
                    $inner->orWhere(fn ($sub) => $sub->matchingOfficeLocationKeywords($branchOnlyKeywords));
                }

                if ($otherKeywords !== []) {
                    $inner->orWhere(fn ($sub) => $sub->matchingOfficeLocationKeywords($otherKeywords));
                }

                if ($dualKeywords !== []) {
                    $inner->orWhere(function ($sub) use ($dualKeywords) {
                        $sub->matchingOfficeLocationKeywords($dualKeywords)
                            ->requiringDualApproval()
                            ->where('first_approval_decision', self::DECISION_APPROVED)
                            ->whereNotNull('first_approver_id');
                    });
                }

                if ($branchOnlyKeywords === [] && $dualKeywords === [] && $otherKeywords === []) {
                    $inner->whereRaw('0 = 1');
                }
            });
    }

    /**
     * 部長向け: 3万円以上かつ同部署（情報システム部以外、社内3万円以上は除く）の承認待ち申請
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApprovableByManager($query, ?string $department)
    {
        return $query
            ->where('application_type', '!=', self::TYPE_INTERNAL_OVER_30K)
            ->where('price_including_tax', '>=', self::MANAGER_APPROVAL_MIN_AMOUNT)
            ->matchingManagerDepartment($department)
            ->excludingBranchManagerOnlyLocations()
            ->notBelongingToInformationSystemsDepartment()
            ->excludingFoodDesignatedApprovals()
            ->where(function ($inner) {
                $inner->where(fn ($sub) => $sub->notRequiringDualApproval())
                    ->orWhere(function ($dual) {
                        $dual->requiringDualApproval()
                            ->whereNull('first_approver_id');
                    });
            });
    }

    /**
     * 食品備品の指定承認ルート（桃谷店・物流センターの3万円以上、緊急対応）を除外
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeExcludingFoodDesignatedApprovals($query)
    {
        $foodKeyword = '%'.self::FOOD_DEPARTMENT_KEYWORD.'%';
        $purchasedTypes = [
            self::TYPE_PURCHASED_UNDER_10K,
            self::TYPE_PURCHASED_OVER_10K,
        ];

        return $query
            ->where(function ($mart) {
                $mart->whereNotIn('delivery_destination', [
                    self::DELIVERY_FOOD_MOMOTANI,
                    self::DELIVERY_FOOD_LOGISTICS,
                ])->orWhereIn('application_type', [
                    self::TYPE_PURCHASED_UNDER_10K,
                    self::TYPE_PURCHASED_OVER_10K,
                ])->orWhere('price_including_tax', '<', self::MANAGER_APPROVAL_MIN_AMOUNT);
            })
            ->where(function ($emergency) use ($purchasedTypes, $foodKeyword) {
                $emergency->whereNotIn('application_type', $purchasedTypes)
                    ->orWhere(fn ($notFood) => $notFood->whereNotMatchingFoodRelatedMarkers($foodKeyword));
            });
    }

    /**
     * @param  Builder<self>  $query
     * @param  array{
     *     department?: ?string,
     *     location?: ?string,
     *     date_from?: ?string,
     *     date_to?: ?string,
     *     keyword?: ?string,
     *     price_min?: ?int,
     *     price_max?: ?int,
     * }  $filters
     * @return Builder<self>
     */
    public function scopeFiltered($query, array $filters)
    {
        $department = $filters['department'] ?? null;
        $location = $filters['location'] ?? null;
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $keyword = $filters['keyword'] ?? null;
        $priceMin = $filters['price_min'] ?? null;
        $priceMax = $filters['price_max'] ?? null;

        return $query
            ->when($department, function ($q) use ($department) {
                $q->where(function ($inner) use ($department) {
                    $inner->where('department', $department)
                        ->orWhere('section', $department)
                        ->orWhereHas('user.affiliationHistories', function ($affiliation) use ($department) {
                            $affiliation->where('department', $department)->currentlyActive();
                        });
                });
            })
            ->when($location, function ($q) use ($location) {
                $q->whereHas('user.affiliationHistories', function ($affiliation) use ($location) {
                    $affiliation->where('location', $location)->currentlyActive();
                });
            })
            ->when($dateFrom, fn ($q) => $q->whereDate('application_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('application_date', '<=', $dateTo))
            ->when($keyword, function ($q) use ($keyword) {
                $like = '%'.$keyword.'%';

                $q->where(function ($inner) use ($like) {
                    $inner->where('product_name', 'like', $like)
                        ->orWhere('remarks', 'like', $like)
                        ->orWhere('approval_memo', 'like', $like)
                        ->orWhere('department', 'like', $like)
                        ->orWhere('section', 'like', $like)
                        ->orWhereHas('user', function ($user) use ($like) {
                            $user->where('name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('first_name', 'like', $like)
                                ->orWhere('employee_id', 'like', $like);
                        });
                });
            })
            ->when($priceMin !== null, fn ($q) => $q->where('price_including_tax', '>=', $priceMin))
            ->when($priceMax !== null, fn ($q) => $q->where('price_including_tax', '<=', $priceMax));
    }
}
