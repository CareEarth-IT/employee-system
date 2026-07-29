<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AssertsProfileAccess;
use App\Http\Requests\AffiliationStoreRequest;
use App\Http\Requests\AffiliationUpdateRequest;
use App\Models\AffiliationHistory;
use App\Models\User;
use App\Services\DriveStaffSyncService;
use App\Support\UserRouteHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AffiliationController extends Controller
{
    use AssertsProfileAccess;

    public function __construct(
        private DriveStaffSyncService $driveStaffSync,
    ) {}

    public function create(?User $user = null): View|RedirectResponse
    {
        $target = $user ?? auth()->user();
        $this->assertCanEditProfile($target);
        $target->load('profile');

        return view('affiliation.create', [
            'user' => $target,
        ]);
    }

    public function store(AffiliationStoreRequest $request, ?User $user = null): RedirectResponse
    {
        $target = $user ?? auth()->user();
        $this->assertCanEditProfile($target);

        $this->syncEmployeeId($request, $target);
        $affiliation = $target->affiliationHistories()->create([
            ...$request->validated(),
            'import_locked' => true,
        ]);

        if ($affiliation->isEnrolled()) {
            $target->closeOtherEnrolledAffiliations($affiliation);
        }

        $target->syncRoleFromAffiliation();
        $this->driveStaffSync->syncUser($target->fresh());

        $message = '所属部署を登録しました。';

        if ($request->input('action') === 'continue') {
            return redirect()
                ->to(UserRouteHelper::route($target, 'affiliations.create', 'users.affiliations.create'))
                ->with('success', $message);
        }

        return $this->redirectToProfileEdit($target, $message);
    }

    public function edit(AffiliationHistory $affiliation): View|RedirectResponse
    {
        $this->assertCanEditProfile($affiliation->user);
        $affiliation->user->load('profile');

        return view('affiliation.edit', [
            'affiliation' => $affiliation,
            'user' => $affiliation->user,
        ]);
    }

    public function update(AffiliationUpdateRequest $request, AffiliationHistory $affiliation): RedirectResponse
    {
        $this->assertCanEditProfile($affiliation->user);

        $this->syncEmployeeId($request, $affiliation->user);
        $affiliation->update([
            ...$request->validated(),
            'import_locked' => true,
        ]);

        if ($affiliation->isEnrolled()) {
            $affiliation->user->closeOtherEnrolledAffiliations($affiliation);
        }

        $affiliation->user->syncRoleFromAffiliation();
        $this->driveStaffSync->syncUser($affiliation->user->fresh());

        return $this->redirectToProfileEdit($affiliation->user, '所属部署を更新しました。');
    }

    public function destroy(AffiliationHistory $affiliation): RedirectResponse
    {
        $user = $affiliation->user;
        $this->assertCanEditProfile($user);

        $affiliation->delete();
        $user->syncRoleFromAffiliation();

        return $this->redirectToProfileEdit($user, '所属部署を削除しました。');
    }

    private function syncEmployeeId(AffiliationStoreRequest $request, User $target): void
    {
        if (! auth()->user()->canEditEmployeeIdentity($target)) {
            return;
        }

        if (! $request->boolean('allow_hr_id_edit')) {
            return;
        }

        $employeeId = $request->input('employee_id');

        if ($employeeId !== null && $employeeId !== '') {
            $target->update([
                'employee_id' => $employeeId,
                'import_locked' => true,
            ]);
        }
    }
}