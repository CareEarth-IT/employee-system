<?php

namespace App\Services;

use App\Models\DashboardLink;
use App\Models\User;
use App\Support\DashboardTab;
use Illuminate\Support\Collection;

class DashboardLinkResolver
{
    /**
     * @return list<DashboardLink>
     */
    public function visibleLinksFor(
        User $user,
        string $tabKey,
        ?string $departmentPortalUrl = null,
        ?string $departmentPortalLabel = null,
        bool $showAttendanceSection = false,
    ): array {
        $links = $this->linksForTab($tabKey, $departmentPortalUrl, $departmentPortalLabel);

        return $links
            ->filter(fn (DashboardLink $link) => $this->isVisibleToUser($link, $user, $showAttendanceSection))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, DashboardLink>
     */
    public function editableLinksFor(
        string $tabKey,
        ?string $departmentPortalUrl = null,
        ?string $departmentPortalLabel = null,
    ): Collection {
        $stored = DashboardLink::query()
            ->forTab($tabKey)
            ->ordered()
            ->get();

        if ($stored->isNotEmpty()) {
            return $stored;
        }

        return $this->builtinLinks($tabKey, $departmentPortalUrl, $departmentPortalLabel);
    }

    /**
     * @return Collection<int, DashboardLink>
     */
    private function linksForTab(
        string $tabKey,
        ?string $departmentPortalUrl,
        ?string $departmentPortalLabel,
    ): Collection {
        $stored = DashboardLink::query()
            ->forTab($tabKey)
            ->ordered()
            ->get();

        if ($stored->isNotEmpty()) {
            return $stored;
        }

        return $this->builtinLinks($tabKey, $departmentPortalUrl, $departmentPortalLabel);
    }

    /**
     * @return Collection<int, DashboardLink>
     */
    private function builtinLinks(
        string $tabKey,
        ?string $departmentPortalUrl,
        ?string $departmentPortalLabel,
    ): Collection {
        $items = match ($tabKey) {
            'common' => [
                [
                    'tab_key' => 'common',
                    'label' => '社員一覧',
                    'url' => route('employees.index'),
                    'kind' => DashboardLink::KIND_LINK,
                    'sort_order' => 10,
                    'is_visible' => true,
                ],
                [
                    'tab_key' => 'common',
                    'label' => '備品購入精算',
                    'url' => route('equipment-purchases.index'),
                    'kind' => DashboardLink::KIND_LINK,
                    'visibility_rule' => 'equipment_purchase',
                    'sort_order' => 20,
                    'is_visible' => true,
                ],
                [
                    'tab_key' => 'common',
                    'label' => '経理の問い合わせ',
                    'url' => route('finance-hr.enter'),
                    'kind' => DashboardLink::KIND_LINK,
                    'sort_order' => 25,
                    'is_visible' => true,
                ],
                [
                    'tab_key' => 'common',
                    'label' => '開発依頼',
                    'url' => route('finance-hr.enter', ['category' => 'is']),
                    'kind' => DashboardLink::KIND_LINK,
                    'sort_order' => 27,
                    'is_visible' => true,
                ],
                [
                    'tab_key' => 'common',
                    'label' => '月次所属記録',
                    'url' => route('monthly-affiliations.index'),
                    'kind' => DashboardLink::KIND_LINK,
                    'visibility_rule' => 'monthly_affiliation',
                    'sort_order' => 30,
                    'is_visible' => true,
                ],
            ],
            'company-car' => [
                [
                    'tab_key' => 'company-car',
                    'label' => '社用車の初めて使用する方はこちら',
                    'kind' => DashboardLink::KIND_FORM_POST,
                    'action_route' => 'drive-app.sync',
                    'sort_order' => 10,
                    'is_visible' => true,
                ],
                [
                    'tab_key' => 'company-car',
                    'label' => '部署が変更された方はこちら',
                    'kind' => DashboardLink::KIND_FORM_POST,
                    'action_route' => 'drive-app.sync',
                    'sort_order' => 20,
                    'is_visible' => true,
                ],
            ],
            default => $this->portalOrAttendanceBuiltinLinks($tabKey, $departmentPortalUrl, $departmentPortalLabel),
        };

        return collect($items)->map(fn (array $attributes) => new DashboardLink($attributes));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function portalOrAttendanceBuiltinLinks(
        string $tabKey,
        ?string $departmentPortalUrl,
        ?string $departmentPortalLabel,
    ): array {
        if ($departmentPortalUrl && $departmentPortalLabel) {
            return [[
                'tab_key' => $tabKey,
                'label' => $departmentPortalLabel,
                'url' => $departmentPortalUrl,
                'kind' => DashboardLink::KIND_LINK,
                'sort_order' => 10,
                'is_visible' => true,
            ]];
        }

        if (in_array($tabKey, ['dispatch', 'specified-skills', 'telecom'], true)) {
            return [[
                'tab_key' => $tabKey,
                'label' => '出勤管理',
                'kind' => DashboardLink::KIND_MODAL,
                'modal_target' => 'attendance-open',
                'sort_order' => 10,
                'is_visible' => true,
            ]];
        }

        return [];
    }

    private function isVisibleToUser(
        DashboardLink $link,
        User $user,
        bool $showAttendanceSection,
    ): bool {
        if (! $link->is_visible) {
            return false;
        }

        if ($link->isModalKind() && ! $showAttendanceSection) {
            return false;
        }

        return match ($link->visibility_rule) {
            'equipment_purchase' => $user->canAccessEquipmentPurchaseSettlement(),
            'monthly_affiliation' => $user->canViewMonthlyAffiliationSnapshots(),
            default => true,
        };
    }
}
