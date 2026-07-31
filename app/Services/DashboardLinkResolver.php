<?php

namespace App\Services;

use App\Models\DashboardLink;
use App\Models\DashboardLinkCategory;
use App\Models\User;
use App\Support\DashboardTab;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DashboardLinkResolver
{
    /**
     * @return list<array{label: ?string, links: list<DashboardLink>}>
     */
    public function visibleLinkGroupsFor(
        User $user,
        string $tabKey,
        ?string $departmentPortalUrl = null,
        ?string $departmentPortalLabel = null,
        bool $showAttendanceSection = false,
    ): array {
        $links = $this->visibleLinksFor(
            $user,
            $tabKey,
            $departmentPortalUrl,
            $departmentPortalLabel,
            $showAttendanceSection,
        );

        if (! DashboardTab::supportsLinkCategories($tabKey)) {
            return [['label' => null, 'links' => $links]];
        }

        $categories = $this->categoriesForTab($tabKey);
        if ($categories->isEmpty()) {
            return [['label' => null, 'links' => $links]];
        }

        $groups = [];
        $assignedKeys = [];

        foreach ($categories as $category) {
            $categoryLinks = array_values(array_filter(
                $links,
                static fn (DashboardLink $link): bool => ($link->category_key ?? '') === $category->category_key,
            ));

            $assignedKeys[] = $category->category_key;
            $groups[] = [
                'label' => $category->label,
                'links' => $categoryLinks,
            ];
        }

        $uncategorized = array_values(array_filter(
            $links,
            static fn (DashboardLink $link): bool => ($link->category_key ?? '') === ''
                || ! in_array($link->category_key, $assignedKeys, true),
        ));

        if ($uncategorized !== []) {
            $groups[] = [
                'label' => null,
                'links' => $uncategorized,
            ];
        }

        return $groups !== [] ? $groups : [['label' => null, 'links' => $links]];
    }

    /**
     * @return Collection<int, DashboardLinkCategory>
     */
    public function editableCategoriesFor(string $tabKey): Collection
    {
        if (! DashboardTab::supportsLinkCategories($tabKey)) {
            return collect();
        }

        return $this->categoriesForTab($tabKey);
    }

    /**
     * @return Collection<int, DashboardLinkCategory>
     */
    private function categoriesForTab(string $tabKey): Collection
    {
        $stored = $this->storedCategoriesForTab($tabKey);
        if ($stored->isNotEmpty()) {
            return $stored;
        }

        return $this->defaultCategories($tabKey);
    }

    /**
     * @return Collection<int, DashboardLinkCategory>
     */
    private function storedCategoriesForTab(string $tabKey): Collection
    {
        if (! Schema::hasTable('dashboard_link_categories')) {
            return collect();
        }

        return DashboardLinkCategory::query()
            ->forTab($tabKey)
            ->ordered()
            ->get();
    }

    /**
     * @return Collection<int, DashboardLinkCategory>
     */
    private function defaultCategories(string $tabKey): Collection
    {
        $defaults = match ($tabKey) {
            'common' => [
                ['category_key' => 'general', 'label' => '共通', 'sort_order' => 10],
                ['category_key' => 'inquiry', 'label' => '問い合わせ', 'sort_order' => 20],
            ],
            'company-car' => [
                ['category_key' => 'procedure', 'label' => '手続き', 'sort_order' => 10],
            ],
            'dispatch', 'specified-skills', 'telecom' => [
                ['category_key' => 'site', 'label' => '社内サイト', 'sort_order' => 10],
                ['category_key' => 'attendance', 'label' => '勤怠', 'sort_order' => 20],
            ],
            'real-estate', 'food', 'beauty' => [
                ['category_key' => 'site', 'label' => '社内サイト', 'sort_order' => 10],
            ],
            default => [
                ['category_key' => 'general', 'label' => 'リンク', 'sort_order' => 10],
            ],
        };

        if ($defaults === []) {
            return collect();
        }

        return collect($defaults)->map(static fn (array $row) => new DashboardLinkCategory([
            'tab_key' => $tabKey,
            ...$row,
        ]));
    }

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
                    'category_key' => 'general',
                    'label' => '社員一覧',
                    'url' => route('employees.index'),
                    'kind' => DashboardLink::KIND_LINK,
                    'sort_order' => 10,
                    'is_visible' => true,
                ],
                [
                    'tab_key' => 'common',
                    'category_key' => 'general',
                    'label' => '備品購入精算',
                    'url' => route('equipment-purchases.index'),
                    'kind' => DashboardLink::KIND_LINK,
                    'visibility_rule' => 'equipment_purchase',
                    'sort_order' => 20,
                    'is_visible' => true,
                ],
                [
                    'tab_key' => 'common',
                    'category_key' => 'inquiry',
                    'label' => '経理の問い合わせ',
                    'url' => route('finance-hr.enter'),
                    'kind' => DashboardLink::KIND_LINK,
                    'sort_order' => 25,
                    'is_visible' => true,
                ],
                [
                    'tab_key' => 'common',
                    'category_key' => 'inquiry',
                    'label' => '開発依頼',
                    'url' => route('finance-hr.enter', ['category' => 'is']),
                    'kind' => DashboardLink::KIND_LINK,
                    'sort_order' => 27,
                    'is_visible' => true,
                ],
                [
                    'tab_key' => 'common',
                    'category_key' => 'general',
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
                    'category_key' => 'procedure',
                    'label' => '社用車の初めて使用する方はこちら',
                    'kind' => DashboardLink::KIND_FORM_POST,
                    'action_route' => 'drive-app.sync',
                    'sort_order' => 10,
                    'is_visible' => true,
                ],
                [
                    'tab_key' => 'company-car',
                    'category_key' => 'procedure',
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
                'category_key' => 'site',
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
                'category_key' => 'attendance',
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
