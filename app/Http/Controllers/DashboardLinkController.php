<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardLinkUpdateRequest;
use App\Models\DashboardLink;
use App\Models\DashboardLinkCategory;
use App\Services\DashboardLinkResolver;
use App\Support\DashboardTab;
use App\Support\DepartmentPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardLinkController extends Controller
{
    public function __construct(
        private DashboardLinkResolver $linkResolver,
    ) {}

    public function edit(Request $request): View
    {
        $tabKey = (string) $request->query('tab', 'common');
        $tab = DashboardTab::findByKey($tabKey) ?? DashboardTab::findByKey('common');
        $department = $tab['department'] ?? '社員共通';

        $this->authorizeDepartment($department);

        $portalUrl = $tabKey !== 'common' && DepartmentPortal::isConfigured($tabKey)
            ? DepartmentPortal::entryUrl($tabKey)
            : null;

        $supportsCategories = DashboardTab::supportsLinkCategories($tabKey);
        $categories = $supportsCategories
            ? $this->linkResolver->editableCategoriesFor($tabKey)
            : collect();

        $categoryOptions = $categories
            ->map(static fn (DashboardLinkCategory $category): array => [
                'category_key' => $category->category_key,
                'label' => $category->label,
            ])
            ->values()
            ->all();

        return view('dashboard.links-form', [
            'tab' => $tab['key'] ?? 'common',
            'tabLabel' => $tab['label'] ?? '社員共通',
            'department' => $department,
            'supportsCategories' => $supportsCategories,
            'categories' => $categories,
            'categoryOptions' => $categoryOptions,
            'links' => $this->linkResolver->editableLinksFor(
                $tab['key'] ?? 'common',
                $portalUrl,
                DepartmentPortal::label($tabKey),
            ),
        ]);
    }

    public function update(DashboardLinkUpdateRequest $request): RedirectResponse
    {
        $tabKey = $request->validated('tab');
        $tab = DashboardTab::findByKey($tabKey) ?? DashboardTab::findByKey('common');
        $department = $tab['department'] ?? '社員共通';

        $this->authorizeDepartment($department);

        DB::transaction(function () use ($request, $tabKey) {
            if (DashboardTab::supportsLinkCategories($tabKey)) {
                DashboardLinkCategory::query()->forTab($tabKey)->delete();

                foreach ($request->validated('categories') ?? [] as $index => $categoryData) {
                    $label = trim((string) ($categoryData['label'] ?? ''));
                    $categoryKey = trim((string) ($categoryData['category_key'] ?? ''));

                    if ($label === '' || $categoryKey === '') {
                        continue;
                    }

                    DashboardLinkCategory::query()->create([
                        'tab_key' => $tabKey,
                        'category_key' => $categoryKey,
                        'label' => $label,
                        'sort_order' => ($index + 1) * 10,
                        'updated_by' => $request->user()->id,
                    ]);
                }
            }

            DashboardLink::query()->forTab($tabKey)->delete();

            foreach ($request->validated('links') ?? [] as $index => $linkData) {
                $label = trim((string) ($linkData['label'] ?? ''));

                if ($label === '') {
                    continue;
                }

                $kind = $linkData['kind'] ?? DashboardLink::KIND_LINK;
                $categoryKey = DashboardTab::supportsLinkCategories($tabKey)
                    ? (trim((string) ($linkData['category_key'] ?? '')) ?: null)
                    : null;

                DashboardLink::query()->create([
                    'tab_key' => $tabKey,
                    'category_key' => $categoryKey,
                    'label' => $label,
                    'url' => $kind === DashboardLink::KIND_LINK
                        ? trim((string) ($linkData['url'] ?? '')) ?: null
                        : null,
                    'kind' => $kind,
                    'action_route' => $kind === DashboardLink::KIND_FORM_POST
                        ? ($linkData['action_route'] ?? null)
                        : null,
                    'modal_target' => $kind === DashboardLink::KIND_MODAL
                        ? ($linkData['modal_target'] ?? null)
                        : null,
                    'visibility_rule' => $linkData['visibility_rule'] ?? null,
                    // Persist the submitted form order (supports drag-and-drop reordering).
                    'sort_order' => ($index + 1) * 10,
                    'is_visible' => filter_var($linkData['is_visible'] ?? false, FILTER_VALIDATE_BOOL),
                    'updated_by' => $request->user()->id,
                ]);
            }
        });

        return redirect()
            ->route('dashboard', ['tab' => $tabKey])
            ->with('success', 'リンクを保存しました。');
    }

    private function authorizeDepartment(string $department): void
    {
        if (! auth()->user()?->canEditDashboardDepartment($department)) {
            abort(403, 'この部署のリンクを編集する権限がありません。');
        }
    }
}
