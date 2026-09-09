<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeHrDetailUpdateRequest;
use App\Models\EmployeeHrDetail;
use App\Models\User;
use App\Services\DriveStaffSyncService;
use App\Services\EmployeeHrDetailCsvExporter;
use App\Support\AffiliationHrDetailSync;
use App\Support\AffiliationResignationSync;
use App\Support\AffiliationStartDateAlignment;
use App\Support\EmployeeHrDetailAccess;
use App\Support\EmployeeIndexQuery;
use App\Support\EmployeeHrDetailFieldGroups;
use App\Support\HrDetailOrgFormState;
use App\Support\UserRouteHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileHrDetailController extends Controller
{
    public function __construct(
        private EmployeeHrDetailCsvExporter $csvExporter,
        private DriveStaffSyncService $driveStaffSync,
    ) {}

    public function edit(?User $user = null): View|RedirectResponse
    {
        $target = $user ?? auth()->user();
        $viewer = auth()->user();

        if (! EmployeeHrDetailAccess::canViewPage($viewer, $target)) {
            abort(403, 'この画面を閲覧する権限がありません。');
        }

        $target->load(['profile', 'hrDetail', 'affiliationHistories']);
        $hrDetail = EmployeeHrDetail::bootstrapForUser($target);

        return view('profile.hr-detail.edit', [
            'user' => $target,
            'hrDetail' => $hrDetail,
            'access' => EmployeeHrDetailAccess::permissions($viewer, $target),
        ]);
    }

    public function update(EmployeeHrDetailUpdateRequest $request, ?User $user = null): RedirectResponse
    {
        $target = $user ?? auth()->user();
        $viewer = auth()->user();

        if (! EmployeeHrDetailAccess::canViewPage($viewer, $target)) {
            abort(403, 'この画面を閲覧する権限がありません。');
        }

        $hrDetail = EmployeeHrDetail::bootstrapForUser($target);
        $editableFields = EmployeeHrDetailAccess::editableFieldNames($viewer, $target);
        $profileFields = array_values(array_intersect(
            $editableFields,
            EmployeeHrDetailFieldGroups::PROFILE_SYNC,
        ));
        $hrDetailFields = array_values(array_diff($editableFields, $profileFields));

        $validated = HrDetailOrgFormState::mergeSectionFields([
            ...$request->validated(),
            'team_primary' => $request->input('team_primary'),
            'team_secondary' => $request->input('team_secondary'),
        ]);
        $hrDetail->update(collect($validated)->only($hrDetailFields)->all());

        if (array_intersect(AffiliationHrDetailSync::HR_ORG_FIELDS, $hrDetailFields) !== []) {
            AffiliationHrDetailSync::syncAffiliationFromHrDetail($target->fresh(), $hrDetail->fresh());
        }

        if (in_array('resigned_at', $hrDetailFields, true)) {
            AffiliationResignationSync::syncFromHrDetail($target->fresh(), $hrDetail->fresh());
            $hrDetail->refresh();
        }

        if (EmployeeHrDetailAccess::canEditCore($viewer, $target)) {
            $this->syncProfileFromHrDetail($target, $hrDetail, collect($validated)->only($profileFields)->all());
        }

        $this->driveStaffSync->syncUser($target->fresh());

        return redirect()
            ->to(UserRouteHelper::route($target, 'profile.hr-detail.edit', 'users.profile.hr-detail.edit'))
            ->with('success', '詳細情報を保存しました。');
    }

    public function exportAll(Request $request): StreamedResponse
    {
        $viewer = auth()->user();
        $this->authorizeExport($viewer);

        $users = EmployeeIndexQuery::forExport($request);

        return response()->streamDownload(
            fn () => $this->csvExporter->streamQuery($users, $viewer),
            $this->csvExporter->filename(),
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function export(?User $user = null): StreamedResponse
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;

        if (! EmployeeHrDetailAccess::canExportCsvForTarget($viewer, $target)) {
            abort(403, 'この社員の詳細情報を出力する権限がありません。');
        }

        $target->load(['profile', 'hrDetail', 'affiliationHistories']);

        return response()->streamDownload(
            fn () => $this->csvExporter->stream(collect([$target]), $viewer),
            $this->csvExporter->filename($target),
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    private function authorizeExport(User $viewer): void
    {
        if (! EmployeeHrDetailAccess::canExportCsv($viewer)) {
            abort(403, '詳細情報のCSV出力権限がありません。');
        }
    }

    /**
     * @param  array<string, mixed>  $profileFields
     */
    private function syncProfileFromHrDetail(User $user, EmployeeHrDetail $hrDetail, array $profileFields = []): void
    {
        if ($profileFields === []) {
            return;
        }

        /** @var list<string> */
        $nullableFields = ['joined_at', 'nationality'];
        $attributes = [];

        foreach ($profileFields as $field => $value) {
            if (in_array($field, $nullableFields, true)) {
                $attributes[$field] = ($value === null || $value === '') ? null : $value;

                continue;
            }

            if ($value !== null && $value !== '') {
                $attributes[$field] = $value;
            }
        }

        if ($hrDetail->name_kana_fullwidth) {
            $attributes['name_kana'] = $hrDetail->name_kana_fullwidth;
        }

        if ($attributes === []) {
            return;
        }

        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id]);
        $previousJoinedAt = $profile->joined_at?->toDateString();
        $profile->update([
            ...$attributes,
            'import_locked' => true,
        ]);

        if (array_key_exists('joined_at', $attributes)
            && $profile->fresh()->joined_at?->toDateString() !== $previousJoinedAt) {
            $user->load('affiliationHistories');
            AffiliationStartDateAlignment::syncForUser($user);
        }

        if (isset($attributes['name_kana'])) {
            $user->update([
                'name' => $hrDetail->name_kana_fullwidth,
                'import_locked' => true,
            ]);
        }
    }
}
