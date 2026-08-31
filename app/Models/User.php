<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_EMPLOYEE = 'employee';

    public const ROLE_HR = 'hr';

    public const ROLE_ADMIN = 'admin';

    /** 人事部とみなす所属「部」のキーワード */
    public const HR_DEPARTMENT_KEYWORD = '人事';

    /**
     * 出勤管理ボタンの対象となる所属「部」のキーワード（部分一致）
     *
     * @var list<string>
     */
    public const ATTENDANCE_DEPARTMENTS = [
        '営業',
        '特定技能',
        '不動産',
        '通信',
    ];

    /** 不動産社内サイトリンクの対象となる所属「部」のキーワード */
    public const REAL_ESTATE_DEPARTMENT_KEYWORD = '不動産';

    /** @var list<string> */
    public const OFFICE_LOCATIONS = [
        '大阪',
        '東京',
        '名古屋',
        '福岡',
        'ベトナム',
    ];

    public const OFFICE_LOCATION_ALL = '全社';

    /**
     * 備品購入の3万円以上承認を支店長のみとする拠点（部長には送らない）
     *
     * @var list<string>
     */
    public const BRANCH_MANAGER_ONLY_OFFICE_LOCATIONS = [
        '福岡',
        '名古屋',
    ];

    /**
     * 備品購入の3万円以上承認を部長→支店長の2段階とする拠点
     *
     * @var list<string>
     */
    public const DUAL_APPROVAL_OFFICE_LOCATIONS = [
        '東京',
    ];

    /** @var list<string> 東京2段階承認の届先（備品の届先） */
    public const DUAL_APPROVAL_TOKYO_DELIVERY_DESTINATIONS = [
        'tokyo_7F',
        'tokyo_9F',
    ];

    /**
     * 東京で2段階承認の対象となる部・課のキーワード（部分一致）
     *
     * @var list<string>
     */
    public const DUAL_APPROVAL_TOKYO_DEPARTMENT_KEYWORDS = [
        '営業部',
        'SS課',
        '管理部',
        'グローバル事業部',
    ];

    /** @var list<string> */
    public const COMPANY_NAMES = [
        'CareEarth',
        'GROWTEC',
        'MidEarth',
        'Earth Management',
    ];

    /** @var list<string> 社員一覧の状況フィルタ（CSV「状況」） */
    public const EMPLOYMENT_STATUS_OPTIONS = [
        '在籍',
        '退職',
        '辞退',
    ];

    /** @var list<string> 社員一覧の雇用形態フィルタ */
    public const EMPLOYMENT_TYPE_OPTIONS = [
        '正社員',
        'アルバイト',
    ];

    /** 備品購入の全権限を持つ所属「部」のキーワード */
    public const GENERAL_AFFAIRS_DEPARTMENT_KEYWORD = '経理部';

    /** 備品購入の全権限を持つ所属「課」のキーワード */
    public const GENERAL_AFFAIRS_SECTION_KEYWORD = '総務課';

    /** 備品購入の部長承認対象となる役職キーワード */
    public const DEPARTMENT_MANAGER_POSITION_KEYWORD = '部長';

    /** 備品購入の支店長承認対象となる役職キーワード */
    public const BRANCH_MANAGER_POSITION_KEYWORD = '支店長';

    /** 人事課とみなす所属「課」のキーワード */
    public const HR_SECTION_KEYWORD = '人事課';

    /** Top Page 編集権限の対象となる所属「課」のキーワード */
    public const ADMINISTRATIVE_AFFAIRS_SECTION_KEYWORD = '庶務課';

    /** 役員とみなす所属・役職のキーワード（現在有効な所属のみ） */
    public const EXECUTIVE_KEYWORD = '役員';

    /** 情報システム部の申請とみなす所属キーワード */
    public const INFORMATION_SYSTEMS_DEPARTMENT_KEYWORD = '情報システム';

    /**
     * Top Page 全部署の閲覧・編集をメールで許可するアカウント（デフォルト）。
     * DASHBOARD_MANAGER_EMAILS で上書き（カンマ区切り）。
     *
     * @var list<string>
     */
    public const DEFAULT_DASHBOARD_MANAGER_EMAILS = [
        'ginga_fukui@careearth.info',
    ];

    /**
     * 経理・人事問い合わせ（finance-hr）担当者画面をメールで許可するアカウント（デフォルト）。
     * FINANCE_HR_ADMIN_EMAILS で上書き（カンマ区切り）。
     *
     * @var list<string>
     */
    public const DEFAULT_FINANCE_HR_ADMIN_EMAILS = [
        'ginga_fukui@careearth.info',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'must_change_password',
        'employee_id',
        'google_id',
        'last_name',
        'first_name',
        'role',
        'import_locked',
        'google_access_token',
        'google_refresh_token',
        'google_token_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_access_token',
        'google_refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'import_locked' => 'boolean',
            'google_token_expires_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'employee_id';
    }

    public function getRouteKey(): mixed
    {
        $employeeId = $this->getAttribute($this->getRouteKeyName());

        if (is_string($employeeId) && $employeeId !== '') {
            return $employeeId;
        }

        return $this->getKey();
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        $user = $this->where($field, $value)->first();

        if ($user !== null) {
            return $user;
        }

        if (ctype_digit((string) $value)) {
            return $this->where($this->getKeyName(), $value)->first();
        }

        return null;
    }

    public function profile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function hrDetail(): HasOne
    {
        return $this->hasOne(EmployeeHrDetail::class);
    }

    public function affiliationHistories(): HasMany
    {
        return $this->hasMany(AffiliationHistory::class)->orderByDesc('start_date');
    }

    public function equipmentPurchaseApplications(): HasMany
    {
        return $this->hasMany(EquipmentPurchaseApplication::class)->orderByDesc('created_at');
    }

    public function attendanceNotifications(): HasMany
    {
        return $this->hasMany(AttendanceNotification::class)->orderByDesc('created_at');
    }

    public function developmentRequests(): HasMany
    {
        return $this->hasMany(DevelopmentRequest::class)->orderByDesc('created_at');
    }

    public function currentAffiliation(): ?AffiliationHistory
    {
        return $this->affiliationHistories()
            ->currentlyActive()
            ->orderByDesc('start_date')
            ->first();
    }

    /**
     * 在籍中の所属が複数ある場合、指定以外を「他へ移動済」にする。
     */
    public function closeOtherEnrolledAffiliations(?AffiliationHistory $except = null): void
    {
        $query = $this->affiliationHistories()
            ->where('enrollment_status', AffiliationHistory::STATUS_ENROLLED);

        if ($except) {
            $query->whereKeyNot($except->id);
        }

        $query->each(fn (AffiliationHistory $history) => $history->closeAsMoved());
    }

    public function displayName(): string
    {
        $profile = $this->relationLoaded('profile')
            ? $this->profile
            : $this->profile()->first();

        if ($profile?->name_kana) {
            return $profile->name_kana;
        }

        if ($profile?->english_name) {
            return $profile->english_name;
        }

        if ($this->last_name || $this->first_name) {
            $name = trim("{$this->last_name} {$this->first_name}");

            if ($name !== '' && $this->last_name !== '未設定') {
                return $name;
            }
        }

        return $this->name;
    }

    /**
     * 備品承認画面の「確認者」表示名。
     * 全部署横断アカウントはログイン名を出さず、入力値または既定ラベルを使う。
     */
    public function equipmentApprovalConfirmName(?string $enteredName = null): string
    {
        if ($this->isGlobalEquipmentApprover()) {
            $entered = trim((string) $enteredName);
            if ($entered !== '') {
                return $entered;
            }

            return (string) env(
                'EQUIPMENT_PURCHASE_GLOBAL_MANAGER_APPROVER_DISPLAY_NAME',
                '上長',
            );
        }

        return $this->displayName();
    }

    /** 社員一覧などで表示する状況（在籍 / 退職 / 辞退） */
    public function displayEmploymentStatus(): string
    {
        $hrStatus = trim((string) ($this->hrDetail?->employment_status ?? ''));

        if (in_array($hrStatus, self::EMPLOYMENT_STATUS_OPTIONS, true)) {
            return $hrStatus;
        }

        if ($hrStatus === AffiliationHistory::STATUS_ENROLLED) {
            return '在籍';
        }

        if ($hrStatus === AffiliationHistory::STATUS_RESIGNED) {
            return '退職';
        }

        $current = $this->currentAffiliation();
        if ($current?->isEnrolled()) {
            return '在籍';
        }

        $hasResigned = $this->relationLoaded('affiliationHistories')
            ? $this->affiliationHistories->contains(
                fn (AffiliationHistory $history) => $history->enrollment_status === AffiliationHistory::STATUS_RESIGNED
            )
            : $this->affiliationHistories()
                ->where('enrollment_status', AffiliationHistory::STATUS_RESIGNED)
                ->exists();

        if ($hasResigned) {
            return '退職';
        }

        return $hrStatus !== '' ? $hrStatus : '—';
    }

    /** 社員一覧などで表示する所属会社 */
    public function displayCompany(): string
    {
        $current = $this->currentAffiliation();
        if ($current?->company) {
            return $current->company;
        }

        $latest = $this->relationLoaded('affiliationHistories')
            ? $this->affiliationHistories->first()
            : $this->affiliationHistories()->orderByDesc('start_date')->first();

        return $latest?->company ?? '—';
    }

    /** 社員一覧などで表示する雇用形態 */
    public function displayEmploymentType(): string
    {
        $hrType = trim((string) ($this->hrDetail?->employment_type ?? ''));
        if ($hrType !== '') {
            return $hrType;
        }

        $position = trim((string) ($this->currentAffiliation()?->position ?? ''));
        if (in_array($position, self::EMPLOYMENT_TYPE_OPTIONS, true)) {
            return $position;
        }

        $latest = $this->relationLoaded('affiliationHistories')
            ? $this->affiliationHistories->first()
            : $this->affiliationHistories()->orderByDesc('start_date')->first();
        $latestPosition = trim((string) ($latest?->position ?? ''));
        if (in_array($latestPosition, self::EMPLOYMENT_TYPE_OPTIONS, true)) {
            return $latestPosition;
        }

        return '—';
    }

    /** 人事部かどうか（users.role が admin、または現在有効な所属「部」に人事を含む） */
    public function isHr(): bool
    {
        return $this->isHrDepartment();
    }

    /** 人事部（部単位）かどうか — 現在有効な所属のみ */
    public function isHrDepartment(): bool
    {
        if ($this->role === self::ROLE_ADMIN) {
            return true;
        }

        $department = $this->currentAffiliation()?->department;

        return $department && str_contains($department, self::HR_DEPARTMENT_KEYWORD);
    }

    /** 人事課（課単位）かどうか — 現在有効な所属のみ */
    public function isHrSection(): bool
    {
        $section = $this->currentAffiliation()?->section;

        return $section && str_contains($section, self::HR_SECTION_KEYWORD);
    }

    /** 役員かどうか — 現在有効な所属の部・課・役職に「役員」を含む */
    public function isExecutive(): bool
    {
        $affiliation = $this->currentAffiliation();

        if ($affiliation === null) {
            return false;
        }

        foreach (['department', 'section', 'position'] as $attribute) {
            $value = $affiliation->{$attribute};

            if (is_string($value) && $value !== '' && str_contains($value, self::EXECUTIVE_KEYWORD)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 所属変更に合わせて users.role を同期する。
     * 人事部以外へ異動した際に role=hr が残り続けないようにする。
     */
    public function syncRoleFromAffiliation(): void
    {
        if ($this->role !== self::ROLE_HR) {
            return;
        }

        if ($this->isHrDepartment()) {
            return;
        }

        $this->forceFill(['role' => self::ROLE_EMPLOYEE])->save();
    }

    /** 現在の所属の部・課・期間を編集できるか（人事部・情報システム部） */
    public function canEditCurrentAffiliationOrg(): bool
    {
        return $this->isHrDepartment() || $this->isInformationSystems();
    }

    /** 情報システム部かどうか */
    public function isInformationSystems(): bool
    {
        $department = $this->currentAffiliation()?->department;

        return $department && str_contains($department, self::INFORMATION_SYSTEMS_DEPARTMENT_KEYWORD);
    }

    /** 庶務課かどうか — 現在有効な所属のみ */
    public function isAdministrativeAffairsSection(): bool
    {
        $section = $this->currentAffiliation()?->section;

        return $section && str_contains($section, self::ADMINISTRATIVE_AFFAIRS_SECTION_KEYWORD);
    }

    /** 経理部かどうか（部単位・現在有効な所属のみ） */
    public function isAccountingDepartment(): bool
    {
        $department = $this->currentAffiliation()?->department;

        return $department && str_contains($department, self::GENERAL_AFFAIRS_DEPARTMENT_KEYWORD);
    }

    /** 月次所属記録の閲覧・保存（経理部・情報システム部のみ） */
    public function canViewMonthlyAffiliationSnapshots(): bool
    {
        return $this->isAccountingDepartment() || $this->isInformationSystems();
    }

    /** 不動産社内サイト（Top Page リンク）の表示対象かどうか */
    public function canViewRealEstatePortalLink(): bool
    {
        return \App\Support\DepartmentPortal::canAccess($this, 'real-estate');
    }

    /** 部署タブの社内サイトリンクの表示対象かどうか（社員共通は対象外） */
    public function canViewDepartmentPortal(string $tabKey): bool
    {
        return \App\Support\DepartmentPortal::canAccess($this, $tabKey);
    }

    /**
     * Top Page のお知らせ・リンク編集
     * （役員・情報システム部・庶務課、または指定メール）
     */
    public function canManageDashboardContents(): bool
    {
        return $this->isExecutive()
            || $this->isInformationSystems()
            || $this->isAdministrativeAffairsSection()
            || $this->isDesignatedDashboardManager();
    }

    /**
     * Top Page 全部署の閲覧・編集を許可されたメールか。
     * DASHBOARD_MANAGER_EMAILS で上書き（カンマ区切り）。未設定時は DEFAULT_DASHBOARD_MANAGER_EMAILS。
     */
    public function isDesignatedDashboardManager(): bool
    {
        $email = strtolower(trim((string) $this->email));

        return $email !== ''
            && in_array($email, self::dashboardManagerEmails(), true);
    }

    /**
     * 経理・人事問い合わせ（finance-hr）担当者画面を閲覧・操作できるか。
     * 人事課・経理部・情報システム部・役員、または指定メール（ginga_fukui 含む）。
     */
    public function canAccessFinanceHrAdmin(): bool
    {
        return $this->isHrSection()
            || $this->isAccountingDepartment()
            || $this->isInformationSystems()
            || $this->isExecutive()
            || $this->isDesignatedFinanceHrAdmin();
    }

    /** 開発依頼の詳細を編集できるか（情報システム部など） */
    public function canEditDevelopmentRequest(): bool
    {
        return $this->affiliationMatchesAnyKeyword(
            config('development_requests.editor_department_keywords', ['情報システム']),
        );
    }

    /** 開発依頼の詳細を閲覧できるか（編集者、または役員など） */
    public function canViewDevelopmentRequestDetail(): bool
    {
        if ($this->canEditDevelopmentRequest()) {
            return true;
        }

        return $this->affiliationMatchesAnyKeyword(
            config('development_requests.viewer_department_keywords', ['役員']),
        );
    }

    /**
     * 現所属の部・課がキーワードのいずれかを含むか。
     *
     * @param  list<string>|mixed  $keywords
     */
    public function affiliationMatchesAnyKeyword(mixed $keywords): bool
    {
        if (! is_array($keywords) || $keywords === []) {
            return false;
        }

        $affiliation = $this->currentAffiliation();
        $haystacks = array_filter([
            $affiliation?->department,
            $affiliation?->section,
        ], static fn ($value): bool => is_string($value) && $value !== '');

        if ($haystacks === []) {
            return false;
        }

        foreach ($keywords as $keyword) {
            $keyword = trim((string) $keyword);
            if ($keyword === '') {
                continue;
            }

            foreach ($haystacks as $haystack) {
                if (str_contains($haystack, $keyword)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * finance-hr 担当者画面をメールで許可されたアカウントか。
     * FINANCE_HR_ADMIN_EMAILS で上書き（カンマ区切り）。未設定時は DEFAULT_FINANCE_HR_ADMIN_EMAILS。
     */
    public function isDesignatedFinanceHrAdmin(): bool
    {
        $email = strtolower(trim((string) $this->email));

        return $email !== ''
            && in_array($email, self::financeHrAdminEmails(), true);
    }

    /**
     * @return list<string>
     */
    public static function dashboardManagerEmails(): array
    {
        $raw = trim((string) env('DASHBOARD_MANAGER_EMAILS', ''));

        if ($raw !== '') {
            $emails = array_values(array_filter(array_map(
                static fn (string $value): string => strtolower(trim($value)),
                explode(',', $raw),
            )));

            if ($emails !== []) {
                return $emails;
            }
        }

        return array_map(
            static fn (string $value): string => strtolower(trim($value)),
            self::DEFAULT_DASHBOARD_MANAGER_EMAILS,
        );
    }

    /**
     * @return list<string>
     */
    public static function financeHrAdminEmails(): array
    {
        $raw = trim((string) env('FINANCE_HR_ADMIN_EMAILS', ''));

        if ($raw !== '') {
            $emails = array_values(array_filter(array_map(
                static fn (string $value): string => strtolower(trim($value)),
                explode(',', $raw),
            )));

            if ($emails !== []) {
                return $emails;
            }
        }

        return array_map(
            static fn (string $value): string => strtolower(trim($value)),
            self::DEFAULT_FINANCE_HR_ADMIN_EMAILS,
        );
    }

    public function canEditDashboardDepartment(string $department): bool
    {
        if (! $this->canManageDashboardContents()) {
            return false;
        }

        return \App\Support\DashboardTab::findByDepartment($department) !== null;
    }

    /** 不動産部かどうか（部単位・現在有効な所属のみ） */
    public function isRealEstateDepartment(): bool
    {
        $department = $this->currentAffiliation()?->department;

        return $department && str_contains($department, self::REAL_ESTATE_DEPARTMENT_KEYWORD);
    }

    /** プロフィール編集: 本人、人事部、役員、または情報システム部 */
    public function canEditProfile(User $target): bool
    {
        if ($this->id === $target->id) {
            return true;
        }

        return $this->isHr() || $this->isExecutive() || $this->isInformationSystems();
    }

    /** 社員ID・社用メールの編集: 情報システム部のみ（全社員対象） */
    public function canEditEmployeeIdentity(?User $target = null): bool
    {
        return $this->isInformationSystems();
    }

    /** 社員新規登録・編集画面: 情報システム部、または人事部・人事課 */
    public function canManageEmployeeRegistry(): bool
    {
        return $this->isInformationSystems()
            || ($this->isHrDepartment() && $this->isHrSection());
    }

    /** 人事部による社員新規登録時に既定パスワードを使う（情シスは画面で入力） */
    public const DEFAULT_REGISTRY_PASSWORD = 'password';

    public function setsDefaultRegistryPasswordOnCreate(): bool
    {
        return $this->canManageEmployeeRegistry() && ! $this->isInformationSystems();
    }

    /**
     * @return list<string>
     */
    public static function registryDepartmentOptions(?string $current = null): array
    {
        return \App\Support\RegistryDepartmentOptions::forSelect($current);
    }

    /**
     * @return list<string>
     */
    public static function registrySectionOptions(?string $current = null): array
    {
        return \App\Support\RegistrySectionOptions::forSelect($current);
    }

    /** プロフィール閲覧時に編集画面へ誘導する（情シス・人事部人事課） */
    public function shouldForceProfileEditMode(User $target): bool
    {
        return $this->canEditProfile($target) && $this->canManageEmployeeRegistry();
    }

    public function canViewAttendanceSection(): bool
    {
        $department = $this->currentAffiliation()?->department;

        if (! $department) {
            return false;
        }

        foreach (self::ATTENDANCE_DEPARTMENTS as $keyword) {
            if (str_contains($department, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /** 備品購入の申請（全社員） */
    public function canAccessEquipmentPurchaseSettlement(): bool
    {
        return true;
    }

    /** 備品購入一覧で自分の申請を閲覧できるか（全社員） */
    public function canViewOwnEquipmentPurchaseList(): bool
    {
        return true;
    }

    /** 備品購入一覧で全申請を閲覧できるか（経理部・総務課のみ） */
    public function seesAllEquipmentPurchaseList(): bool
    {
        return $this->isGeneralAffairs();
    }

    public function canViewEquipmentPurchaseList(): bool
    {
        return $this->seesAllEquipmentPurchaseList() || $this->canViewOwnEquipmentPurchaseList();
    }

    public function canViewEquipmentPurchaseApplication(EquipmentPurchaseApplication $application): bool
    {
        if ($this->seesAllEquipmentPurchaseList()) {
            return true;
        }

        if (! $this->canViewOwnEquipmentPurchaseList()) {
            return false;
        }

        return $application->user_id === $this->id;
    }

    /** 経理部・総務課かどうか（備品購入の全権限）— 現在有効な所属のみ */
    public function isGeneralAffairs(): bool
    {
        $affiliation = $this->currentAffiliation();

        if (! $affiliation) {
            return false;
        }

        $department = $affiliation->department;
        $section = $affiliation->section;

        return $department
            && str_contains($department, self::GENERAL_AFFAIRS_DEPARTMENT_KEYWORD)
            && $section
            && str_contains($section, self::GENERAL_AFFAIRS_SECTION_KEYWORD);
    }

    /** 現在の役職が部長かどうか（本部長・副部長は除く） */
    public function isDepartmentManager(): bool
    {
        $position = $this->currentAffiliation()?->position;

        if (! $position) {
            return false;
        }

        if (str_contains($position, '本部長') || str_contains($position, '副部長')) {
            return false;
        }

        return str_contains($position, self::DEPARTMENT_MANAGER_POSITION_KEYWORD);
    }

    /** 現在の役職が支店長かどうか */
    public function isBranchManager(): bool
    {
        $position = $this->currentAffiliation()?->position;

        if (! $position) {
            return false;
        }

        return str_contains($position, self::BRANCH_MANAGER_POSITION_KEYWORD);
    }

    /** 備品購入の上長承認対象となる部長または支店長かどうか */
    public function isDepartmentOrBranchManager(): bool
    {
        return $this->isDepartmentManager() || $this->isBranchManager();
    }

    /** 所属「部」が情報システム部かどうか */
    public function isInformationSystemsDepartment(?string $department): bool
    {
        return $department && str_contains($department, self::INFORMATION_SYSTEMS_DEPARTMENT_KEYWORD);
    }

    /** 情報システム部申請の指定承認者か（メールアドレスで判定） */
    public function isInformationSystemsRepresentative(): bool
    {
        return $this->isDesignatedInformationSystemsApprover();
    }

    public function isDesignatedInformationSystemsApprover(): bool
    {
        $email = strtolower(trim((string) $this->email));

        return $email !== ''
            && in_array($email, EquipmentPurchaseApplication::informationSystemsApproverEmails(), true);
    }

    public function isDesignatedInternalOver30kApprover(): bool
    {
        $email = strtolower(trim((string) $this->email));

        return $email !== ''
            && in_array($email, EquipmentPurchaseApplication::internalOver30kApproverEmails(), true);
    }

    /** 食品備品の指定承認者か（桃谷店・物流センター・緊急対応のいずれか） */
    public function isDesignatedFoodApprover(): bool
    {
        $email = strtolower(trim((string) $this->email));

        return $email !== ''
            && in_array($email, EquipmentPurchaseApplication::allFoodDesignatedApproverEmails(), true);
    }

    public function isDesignatedFoodApproverFor(EquipmentPurchaseApplication $application): bool
    {
        $email = strtolower(trim((string) $this->email));

        return $email !== ''
            && in_array($email, $application->foodDesignatedApproverEmails(), true);
    }

    /** 上長以上の備品申請を全部署で承認できる指定アカウントか */
    public function isGlobalEquipmentApprover(): bool
    {
        $email = strtolower(trim((string) $this->email));

        return $email !== ''
            && in_array($email, EquipmentPurchaseApplication::globalManagerApproverEmails(), true);
    }

    /** 部長・支店長承認の対象者か（情報システム部は指定承認者のみ、それ以外は部長または支店長） */
    public function canActAsDepartmentApprover(): bool
    {
        if ($this->isGlobalEquipmentApprover() || $this->isDesignatedInformationSystemsApprover()) {
            return true;
        }

        if (! $this->isDepartmentOrBranchManager()) {
            return false;
        }

        return ! $this->isInformationSystemsDepartment($this->currentDepartment());
    }

    private function approvesByDepartmentRule(EquipmentPurchaseApplication $application): bool
    {
        if ($this->isGlobalEquipmentApprover() && $application->requiresSuperiorApproval()) {
            return true;
        }

        if ($application->requiresInternalOver30kApprover()) {
            return $this->isDesignatedInternalOver30kApprover();
        }

        if ($application->belongsToInformationSystemsDepartment()) {
            return $this->isDesignatedInformationSystemsApprover();
        }

        if (! $application->requiresManagerApproval()) {
            return false;
        }

        if ($application->requiresDualApproval()) {
            if ($application->isAwaitingSecondApproval()) {
                return $this->isBranchManager()
                    && $application->matchesOfficeLocationKeywords($this->branchOfficeKeywords());
            }

            if ($application->isAwaitingFirstApproval()) {
                return $this->isDepartmentManager()
                    && $application->belongsToDepartment($this->currentDepartment());
            }

            return false;
        }

        if ($this->isDepartmentManager()
            && ! $application->requiresBranchManagerOnlyApproval()
            && $application->belongsToDepartment($this->currentDepartment())) {
            return true;
        }

        return $this->isBranchManager()
            && $application->matchesOfficeLocationKeywords($this->branchOfficeKeywords());
    }

    /**
     * 支店長の拠点キーワード（例: 福岡支店 → 福岡）
     *
     * @return list<string>
     */
    public function branchOfficeKeywords(): array
    {
        $affiliation = $this->currentAffiliation();
        if ($affiliation === null) {
            return [];
        }

        $keywords = [];

        foreach (self::OFFICE_LOCATIONS as $location) {
            if ($affiliation->location === $location) {
                $keywords[] = $location;
                continue;
            }

            if ($affiliation->department && str_contains($affiliation->department, $location)) {
                $keywords[] = $location;
            }
        }

        return array_values(array_unique($keywords));
    }

    public function currentDepartment(): ?string
    {
        return $this->currentAffiliation()?->department;
    }

    /** 部長承認: 経理部・総務課は3万円未満（情報システム部以外）、社内3万円以上は指定承認者、部長・支店長はその他の3万円以上かつ同部署、情報システム部指定承認者は情報システム部の申請すべて。全部署横断アカウントは上長以上を全部署で承認可。食品備品は桃谷店・物流センター・緊急対応の指定承認者 */
    public function canApproveEquipmentPurchase(EquipmentPurchaseApplication $application): bool
    {
        if ($this->isGlobalEquipmentApprover() && $application->requiresSuperiorApproval()) {
            return true;
        }

        if ($application->requiresFoodDesignatedApprover()) {
            return $this->isDesignatedFoodApproverFor($application);
        }

        if ($application->requiresInternalOver30kApprover()) {
            return $this->isDesignatedInternalOver30kApprover();
        }

        if (! $this->canManageEquipmentPurchases()) {
            return false;
        }

        if ($this->isGeneralAffairs()) {
            return $application->requiresGeneralAffairsApproval();
        }

        return $this->approvesByDepartmentRule($application);
    }

    /**
     * 備品購入の承認待ち・承認処理へアクセスできるか
     * （経理部・総務課、部長・支店長、社内3万円以上の指定承認者、情報システム部の指定承認者、食品備品の指定承認者、または全部署横断の上長承認者）
     */
    public function canManageEquipmentPurchases(): bool
    {
        return $this->isGeneralAffairs()
            || $this->isDesignatedInternalOver30kApprover()
            || $this->isDesignatedFoodApprover()
            || $this->isGlobalEquipmentApprover()
            || $this->canActAsDepartmentApprover();
    }

    /** 申請詳細の発注情報（注文日・到着日・領収書）を更新できるか（経理部総務課のみ） */
    public function canUpdateEquipmentPurchaseOrder(EquipmentPurchaseApplication $application): bool
    {
        return $this->isGeneralAffairs()
            && $this->canViewEquipmentPurchaseApplication($application);
    }

    /** 備品一覧の消耗品フラグを更新できるか（経理部・総務課のみ） */
    public function canUpdateEquipmentPurchaseConsumable(EquipmentPurchaseApplication $application): bool
    {
        return $this->isGeneralAffairs()
            && $this->canViewEquipmentPurchaseApplication($application);
    }

    /** @deprecated Use isDepartmentManager() */
    public function isManager(): bool
    {
        return $this->isDepartmentManager();
    }
}
