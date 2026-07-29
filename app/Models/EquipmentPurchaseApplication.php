<?php

namespace App\Models;

use App\Models\Concerns\EquipmentPurchase\HasApprovalRules;
use App\Models\Concerns\EquipmentPurchase\HasApprovalScopes;
use App\Models\Concerns\EquipmentPurchase\HasApproverEmails;
use App\Models\Concerns\EquipmentPurchase\HasPresentation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * approval_decision と status は別カラム。
 * DECISION_APPROVED と STATUS_APPROVED はどちらも値 'approved' だが用途が異なる。
 */
class EquipmentPurchaseApplication extends Model
{
    use HasApprovalRules;
    use HasApprovalScopes;
    use HasApproverEmails;
    use HasPresentation;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** 承認フォームの判定値（approval_decision カラム） */
    public const DECISION_APPROVED = 'approved';

    public const DECISION_DENIED = 'denied';

    /** @var array<string, string> */
    public const APPROVAL_DECISIONS = [
        self::DECISION_APPROVED => '許可',
        self::DECISION_DENIED => '許可しない',
    ];

    public const TYPE_INTERNAL_UNDER_30K = 'internal_under_30k';

    public const TYPE_INTERNAL_OVER_30K = 'internal_over_30k';

    public const TYPE_ONSITE_UNDER_30K = 'onsite_under_30k';

    public const TYPE_ONSITE_OVER_30K = 'onsite_over_30k';

    public const TYPE_PURCHASED_UNDER_10K = 'purchased_under_10k';

    public const TYPE_PURCHASED_OVER_10K = 'purchased_over_10k';

    /** @deprecated Use internalOver30kApproverEmails() */
    public const INTERNAL_OVER_30K_APPROVER_EMAIL = 'takuya_nishi@careearth.info';

    /** @deprecated Use informationSystemsApproverEmails() */
    public const INFORMATION_SYSTEMS_APPROVER_EMAIL = 'mariko_nakamoto@careearth.info';

    /** @deprecated Use globalManagerApproverEmails() */
    public const GLOBAL_MANAGER_APPROVER_EMAIL = 'employee@gmail.com';

    /** 部長承認が必要な最低金額・申請タイプの閾値（税込） */
    public const MANAGER_APPROVAL_MIN_AMOUNT = 30000;

    /** @var list<string> */
    public const UNDER_30K_TYPES = [
        self::TYPE_INTERNAL_UNDER_30K,
        self::TYPE_ONSITE_UNDER_30K,
        self::TYPE_PURCHASED_UNDER_10K,
    ];

    /** @var list<string> */
    public const OVER_30K_TYPES = [
        self::TYPE_INTERNAL_OVER_30K,
        self::TYPE_ONSITE_OVER_30K,
        self::TYPE_PURCHASED_OVER_10K,
    ];

    /**
     * 申請タイプと価格（税込）が一致するか
     */
    public static function priceMatchesApplicationType(string $applicationType, int $price): bool
    {
        if (in_array($applicationType, self::UNDER_30K_TYPES, true)) {
            return $price < self::MANAGER_APPROVAL_MIN_AMOUNT;
        }

        if (in_array($applicationType, self::OVER_30K_TYPES, true)) {
            return $price >= self::MANAGER_APPROVAL_MIN_AMOUNT;
        }

        return true;
    }

    /**
     * 申請タイプに対する価格（税込）のエラーメッセージ
     */
    public static function priceValidationMessageForType(string $applicationType): ?string
    {
        if (in_array($applicationType, self::UNDER_30K_TYPES, true)) {
            $max = number_format(self::MANAGER_APPROVAL_MIN_AMOUNT - 1);

            return "「3万円未満」の申請タイプでは、価格（税込）は{$max}円以下で入力してください。";
        }

        if (in_array($applicationType, self::OVER_30K_TYPES, true)) {
            $min = number_format(self::MANAGER_APPROVAL_MIN_AMOUNT);

            return "「3万円以上」の申請タイプでは、価格（税込）は{$min}円以上で入力してください。";
        }

        return null;
    }

    /** @var array<string, string> */
    public const TYPE_LABELS = [
        self::TYPE_INTERNAL_UNDER_30K => '社内の備品 購入申請：3万円未満',
        self::TYPE_INTERNAL_OVER_30K => '社内の備品 購入申請：3万円以上',
        self::TYPE_ONSITE_UNDER_30K => '現場用 備品 購入申請：3万円未満',
        self::TYPE_ONSITE_OVER_30K => '現場用 備品 購入申請：3万円以上',
        self::TYPE_PURCHASED_UNDER_10K => '現場での緊急購入 報告：3万円未満',
        self::TYPE_PURCHASED_OVER_10K => '現場での緊急購入 報告：3万円以上',
    ];

    /** @var list<array{label: string, types: array<string, string>}> */
    public const TYPE_GROUPS = [
        [
            'label' => '社内の備品 購入申請',
            'types' => [
                self::TYPE_INTERNAL_UNDER_30K => '3万円未満',
                self::TYPE_INTERNAL_OVER_30K => '3万円以上',
            ],
        ],
        [
            'label' => '現場用 備品 購入申請',
            'types' => [
                self::TYPE_ONSITE_UNDER_30K => '3万円未満',
                self::TYPE_ONSITE_OVER_30K => '3万円以上',
            ],
        ],
        [
            'label' => '現場での緊急購入 報告',
            'types' => [
                self::TYPE_PURCHASED_UNDER_10K => '3万円未満',
                self::TYPE_PURCHASED_OVER_10K => '3万円以上',
            ],
        ],
    ];

    /** @var list<string> */
    public const PURCHASE_SITES = [
        'Amazon',
        'モノタロウ',
        '楽天',
        'アスクル',
    ];

    public const DESTINATION_SECTION_ONLY = 'section_only';

    public const DESTINATION_DEPARTMENT_ALL = 'department_all';

    public const DESTINATION_LOCATION_ALL = 'location_all';

    public const DESTINATION_CE_GROUP_ALL = 'ce_group_all';

    public const DESTINATION_ONSITE = 'onsite';

    /** @var array<string, string> */
    public const ITEM_DESTINATIONS = [
        self::DESTINATION_SECTION_ONLY => '部・課専用',
        self::DESTINATION_DEPARTMENT_ALL => '部全体用',
        self::DESTINATION_LOCATION_ALL => '拠点全体',
        self::DESTINATION_CE_GROUP_ALL => 'CE-Group 全体',
        self::DESTINATION_ONSITE => '現場',
    ];

    public const DELIVERY_OTHER = 'other';

    /** @var array<string, string> */
    public const DELIVERY_DESTINATIONS = [
        'osaka_2f' => '大阪（2F）',
        'osaka_3f' => '大阪（3F）',
        'osaka_4f' => '大阪（4F）',
        'osaka_9f' => '大阪（9F）',
        'tokyo_7F' => '東京7F',
        'tokyo_9F' => '東京9F',
        'nagoya' => '名古屋',
        'fukuoka' => '福岡',
        'mart_momotani' => '桃谷店',
        'mart_souko' => '物流センター',
        'beauty_business' => '美容事業',
        self::DELIVERY_OTHER => 'その他',
    ];

    /** 食品備品の届先キー（桃谷店） */
    public const DELIVERY_FOOD_MOMOTANI = 'mart_momotani';

    /** 食品備品の届先キー（物流センター） */
    public const DELIVERY_FOOD_LOGISTICS = 'mart_souko';

    public const FOOD_DEPARTMENT_KEYWORD = '食品';

    /** @deprecated Use foodMomotaniOver30kApproverEmails() */
    public const FOOD_MOMOTANI_OVER_30K_APPROVER_EMAIL = 'nguyenphuong_tien@careearth.info';

    /** @deprecated Use foodEmergencyUnder30kApproverEmails() / foodLogisticsOver30kApproverEmails() */
    public const FOOD_SUGIURA_APPROVER_EMAIL = 'kanji_sugiura@careearth.info';

    /** @deprecated Use foodEmergencyOver30kApproverEmails() */
    public const FOOD_EMERGENCY_OVER_30K_APPROVER_EMAIL = 'buicuongthinh@careearth.info';

    public const URGENCY_NO_SHIPPING_ASAP = 'no_shipping_asap';

    public const URGENCY_ASAP = 'asap';

    public const URGENCY_FEW_DAYS = 'few_days';

    public const URGENCY_ONE_WEEK = 'one_week';

    public const URGENCY_NO_RUSH = 'no_rush';

    /** @var array<string, string> */
    public const PURCHASE_URGENCIES = [
        self::URGENCY_NO_SHIPPING_ASAP => '送料はかからなくて、急ぎの購入希望',
        self::URGENCY_ASAP => '送料がかかります。送料が掛かっても、なるべく急ぎの購入希望です。',
        self::URGENCY_FEW_DAYS => '送料がかかります。数日後の注文で大丈夫。複数商品まとめて購入し送料を削減',
        self::URGENCY_ONE_WEEK => '1週間前後の注文で大丈夫。複数の備品をまとめて購入し送料を削減',
        self::URGENCY_NO_RUSH => '注文を急がないが必要な備品',
    ];

    protected $fillable = [
        'user_id',
        'application_type',
        'application_date',
        'order_date',
        'arrival_date',
        'receipt_issued',
        'is_consumable',
        'orderer_id',
        'purchase_site',
        'purchase_site_other',
        'purchase_site_url',
        'product_name',
        'size',
        'color_model',
        'quantity',
        'price_including_tax',
        'remarks',
        'purchase_reason',
        'item_destination',
        'department',
        'section',
        'onsite_name',
        'delivery_destination',
        'delivery_zip',
        'delivery_address',
        'delivery_recipient_name',
        'delivery_recipient_phone',
        'purchase_urgency',
        'status',
        'approved_at',
        'approver_id',
        'approver_display_name',
        'approval_decision',
        'approval_memo',
        'first_approved_at',
        'first_approver_id',
        'first_approver_display_name',
        'first_approval_decision',
        'first_approval_memo',
    ];

    protected function casts(): array
    {
        return [
            'application_date' => 'date',
            'order_date' => 'date',
            'arrival_date' => 'date',
            'receipt_issued' => 'boolean',
            'is_consumable' => 'boolean',
            'approved_at' => 'datetime',
            'first_approved_at' => 'datetime',
            'quantity' => 'integer',
            'price_including_tax' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function firstApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'first_approver_id');
    }

    public function orderer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'orderer_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
