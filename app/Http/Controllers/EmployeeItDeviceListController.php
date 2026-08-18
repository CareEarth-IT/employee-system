<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeHrDetailUpdateRequest;
use App\Models\EmployeeHrDetail;
use App\Models\User;
use App\Services\DriveStaffSyncService;
use App\Services\EmployeeItDeviceListService;
use App\Support\EmployeeHrDetailAccess;
use App\Support\EmployeeHrDetailFieldGroups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeItDeviceListController extends Controller
{
    public function __construct(
        private readonly EmployeeItDeviceListService $itDeviceList,
        private readonly DriveStaffSyncService $driveStaffSync,
    ) {}

    public function index(Request $request): View
    {
        $viewer = $request->user();

        if ($viewer === null || ! EmployeeHrDetailAccess::canViewItDeviceList($viewer)) {
            abort(403, 'IT・デバイス一覧を閲覧する権限がありません。');
        }

        $status = trim((string) $request->query('status', '在籍'));
        $keyword = trim((string) $request->query('keyword', ''));

        if (! in_array($status, EmployeeItDeviceListService::STATUS_TABS, true)) {
            $status = '在籍';
        }

        $rows = $this->itDeviceList->rows($viewer, $status, $keyword);

        return view('it-devices.index', [
            'rows' => $rows,
            'status' => $status,
            'keyword' => $keyword,
            'statusTabs' => EmployeeItDeviceListService::STATUS_TABS,
        ]);
    }

    public function show(Request $request, User $user): View
    {
        $viewer = $request->user();
        $this->authorizeItDeviceList($viewer);
        $this->authorizeItSectionView($viewer, $user);

        $user->load(['profile', 'hrDetail']);
        $hrDetail = EmployeeHrDetail::bootstrapForUser($user);
        $access = EmployeeHrDetailAccess::permissions($viewer, $user);

        return view('it-devices._panel', [
            'user' => $user,
            'hrDetail' => $hrDetail,
            'access' => $access,
            'status' => trim((string) $request->query('status', '在籍')),
            'keyword' => trim((string) $request->query('keyword', '')),
        ]);
    }

    public function update(EmployeeHrDetailUpdateRequest $request, User $user): RedirectResponse
    {
        $viewer = auth()->user();
        $this->authorizeItDeviceList($viewer);

        if (! EmployeeHrDetailAccess::canEditIt($viewer, $user)) {
            abort(403, 'IT・デバイス情報を編集する権限がありません。');
        }

        $hrDetail = EmployeeHrDetail::bootstrapForUser($user);
        $editableFields = array_values(array_intersect(
            EmployeeHrDetailAccess::editableFieldNames($viewer, $user),
            EmployeeHrDetailFieldGroups::IT,
        ));

        $hrDetail->update($request->safe()->only($editableFields));
        $this->driveStaffSync->syncUser($user->fresh());

        return redirect()
            ->route('it-devices.index', array_filter([
                'status' => $request->input('status'),
                'keyword' => $request->input('keyword'),
            ], fn ($value) => is_string($value) && $value !== ''))
            ->with('success', $user->displayName().' さんの IT・デバイス情報を保存しました。');
    }

    private function authorizeItDeviceList(?User $viewer): void
    {
        if ($viewer === null || ! EmployeeHrDetailAccess::canViewItDeviceList($viewer)) {
            abort(403, 'IT・デバイス一覧を閲覧する権限がありません。');
        }
    }

    private function authorizeItSectionView(?User $viewer, User $target): void
    {
        if ($viewer === null || ! EmployeeHrDetailAccess::canViewIt($viewer, $target)) {
            abort(403, 'IT・デバイス情報を閲覧する権限がありません。');
        }
    }
}
