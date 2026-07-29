<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardContentRequest;
use App\Models\DashboardContent;
use App\Support\DashboardTab;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardContentController extends Controller
{
    public function createAnnouncement(Request $request): View
    {
        $tabKey = (string) $request->query('tab', 'common');
        $tab = DashboardTab::findByKey($tabKey) ?? DashboardTab::findByKey('common');
        $department = $tab['department'] ?? '社員共通';

        $this->authorizeDepartment($department);

        return view('dashboard.announcement-form', [
            'content' => new DashboardContent([
                'department' => $department,
                'is_visible' => true,
            ]),
            'department' => $department,
            'tab' => $tab['key'] ?? 'common',
            'action' => route('dashboard.announcements.store'),
            'method' => 'POST',
            'submitLabel' => '保存',
        ]);
    }

    public function storeAnnouncement(DashboardContentRequest $request): RedirectResponse
    {
        $department = $request->validated('department');
        $this->authorizeDepartment($department);

        DashboardContent::persistHtml(
            $department,
            $request->validated('content_html'),
            $request->user()->id,
            $request->validated('page_url'),
            null,
            $request->validated('is_visible'),
        );

        return redirect()
            ->route('dashboard', ['tab' => $request->input('tab', DashboardTab::findByDepartment($department)['key'] ?? 'common')])
            ->with('success', 'お知らせを登録しました。');
    }

    public function editAnnouncement(Request $request, DashboardContent $dashboardContent): View
    {
        $this->authorizeDepartment($dashboardContent->department);

        $tab = DashboardTab::findByDepartment($dashboardContent->department);

        return view('dashboard.announcement-form', [
            'content' => $dashboardContent,
            'department' => $dashboardContent->department,
            'tab' => $request->query('tab', $tab['key'] ?? 'common'),
            'action' => route('dashboard.announcements.update', $dashboardContent),
            'method' => 'PUT',
            'submitLabel' => '保存',
        ]);
    }

    public function updateAnnouncement(DashboardContentRequest $request, DashboardContent $dashboardContent): RedirectResponse
    {
        $this->authorizeDepartment($dashboardContent->department);

        DashboardContent::persistHtml(
            $request->validated('department'),
            $request->validated('content_html'),
            $request->user()->id,
            $request->validated('page_url'),
            $dashboardContent->id,
            $request->validated('is_visible'),
        );

        return redirect()
            ->route('dashboard', ['tab' => $request->input('tab', DashboardTab::findByDepartment($dashboardContent->department)['key'] ?? 'common')])
            ->with('success', 'お知らせを更新しました。');
    }

    public function destroyAnnouncement(Request $request, DashboardContent $dashboardContent): RedirectResponse
    {
        $department = $dashboardContent->department;
        $this->authorizeDepartment($department);

        if ($dashboardContent->content_path) {
            \App\Services\DashboardContentStorage::delete($dashboardContent->content_path);
        }

        $dashboardContent->delete();

        $tab = DashboardTab::findByDepartment($department);

        return redirect()
            ->route('dashboard', ['tab' => $request->input('tab', $tab['key'] ?? 'common')])
            ->with('success', 'お知らせを削除しました。');
    }

    private function authorizeDepartment(string $department): void
    {
        if (! auth()->user()?->canEditDashboardDepartment($department)) {
            abort(403, 'この部署のコンテンツを編集する権限がありません。');
        }
    }
}
