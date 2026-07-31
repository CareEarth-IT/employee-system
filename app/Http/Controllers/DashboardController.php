<?php

namespace App\Http\Controllers;

use App\Models\DashboardContent;
use App\Services\DashboardLinkResolver;
use App\Support\DashboardTab;
use App\Support\DepartmentPortal;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardLinkResolver $linkResolver,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $tabs = DashboardTab::tabsFor($user);

        $requestedTab = (string) $request->query('tab', DashboardTab::defaultTabKeyFor($user));
        $activeTab = DashboardTab::findByKey($requestedTab) !== null
            ? $requestedTab
            : 'common';

        $activeTabConfig = DashboardTab::findByKey($activeTab) ?? DashboardTab::findByKey('common');
        $department = $activeTabConfig['department'] ?? '社員共通';
        $canViewActiveTabContent = DashboardTab::canViewTab($user, $activeTab)
            || $user->canManageDashboardContents();

        $contents = $canViewActiveTabContent
            ? DashboardContent::query()
                ->visible()
                ->forDepartment($department)
                ->orderBy('id')
                ->get()
            : collect();

        $editableContent = $canViewActiveTabContent
            ? DashboardContent::query()
                ->visible()
                ->forDepartment($department)
                ->orderByDesc('updated_at')
                ->first()
            : null;

        $departmentPortalUrl = $activeTab !== 'common' && DepartmentPortal::canAccess($user, $activeTab)
            ? DepartmentPortal::entryUrl($activeTab)
            : null;

        return view('dashboard', [
            'user' => $user,
            'tabs' => $tabs,
            'activeTab' => $activeTab,
            'activeTabLabel' => $activeTabConfig['label'] ?? '社員共通',
            'activeDepartment' => $department,
            'contents' => $contents,
            'canEditActiveTab' => $user->canEditDashboardDepartment($department),
            'canViewActiveTabContent' => $canViewActiveTabContent,
            'showAttendanceSection' => $user->canViewAttendanceSection(),
            'tabLinks' => $this->linkResolver->visibleLinksFor(
                $user,
                $activeTab,
                $departmentPortalUrl,
                DepartmentPortal::label($activeTab),
                $user->canViewAttendanceSection(),
            ),
            'tabLinkGroups' => $this->linkResolver->visibleLinkGroupsFor(
                $user,
                $activeTab,
                $departmentPortalUrl,
                DepartmentPortal::label($activeTab),
                $user->canViewAttendanceSection(),
            ),
            'departmentPortalUrl' => $departmentPortalUrl,
            'departmentPortalLabel' => DepartmentPortal::label($activeTab),
        ]);
    }
}
