<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AssertsProfileAccess;
use App\Http\Requests\AffiliationStoreRequest;
use App\Http\Requests\AffiliationUpdateRequest;
use App\Models\AffiliationHistory;
use App\Models\User;
use App\Services\DriveStaffSyncService;
use App\Support\AffiliationHrDetailSync;
use App\Support\UserRouteHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AffiliationController extends Controller
{
    use AssertsProfileAccess;

    public function __construct(
        private DriveStaffSyncService $driveStaffSync,
    ) {}

    public function create(?User $user = null): View|Response|RedirectResponse
    {
        $target = $user ?? auth()->user();
        $this->assertCanManageAffiliation($target);
        $this->rememberAffiliationReturnUser($target);
        $target->load('profile');

        return $this->noStoreView('affiliation.create', [
            'user' => $target,
            'hasOtherCurrentAffiliations' => $target->affiliationHistories()
                ->currentlyActive()
                ->exists(),
        ]);
    }

    public function store(AffiliationStoreRequest $request, ?User $user = null): Response
    {
        $target = $user ?? auth()->user();
        $this->assertCanManageAffiliation($target);

        $this->syncEmployeeId($request, $target);
        $affiliation = $target->affiliationHistories()->create([
            ...$request->validated(),
            'import_locked' => true,
        ]);

        $this->afterSaveAffiliation($request, $target, $affiliation);

        $target->syncRoleFromAffiliation();
        $this->driveStaffSync->syncUser($target->fresh());

        $message = '所属部署を登録しました。';

        if ($request->input('action') === 'continue') {
            return $this->replaceLocationTo(
                UserRouteHelper::route($target, 'affiliations.create', 'users.affiliations.create'),
                $target,
                $message,
            );
        }

        return $this->redirectToProfileEdit($target, $message);
    }

    public function edit(AffiliationHistory $affiliation): View|Response|RedirectResponse
    {
        $this->assertCanManageAffiliation($affiliation->user);
        $this->rememberAffiliationReturnUser($affiliation->user);
        $affiliation->user->load('profile');

        return $this->noStoreView('affiliation.edit', [
            'affiliation' => $affiliation,
            'user' => $affiliation->user,
            'hasOtherCurrentAffiliations' => $affiliation->user->affiliationHistories()
                ->currentlyActive()
                ->whereKeyNot($affiliation->id)
                ->exists(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function noStoreView(string $view, array $data): Response
    {
        return response()
            ->view($view, $data)
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    public function update(AffiliationUpdateRequest $request, AffiliationHistory $affiliation): Response
    {
        $this->assertCanManageAffiliation($affiliation->user);

        $this->syncEmployeeId($request, $affiliation->user);
        $affiliation->update([
            ...$request->validated(),
            'import_locked' => true,
        ]);

        $this->afterSaveAffiliation($request, $affiliation->user, $affiliation->fresh());

        $affiliation->user->syncRoleFromAffiliation();
        $this->driveStaffSync->syncUser($affiliation->user->fresh());

        return $this->redirectToProfileEdit($affiliation->user, '所属部署を更新しました。');
    }

    public function destroy(AffiliationHistory $affiliation): Response
    {
        $user = $affiliation->user;
        $this->assertCanManageAffiliation($user);

        $affiliation->delete();
        $user->syncRoleFromAffiliation();

        return $this->redirectToProfileEdit($user, '所属部署を削除しました。');
    }

    private function afterSaveAffiliation(
        AffiliationStoreRequest $request,
        User $target,
        AffiliationHistory $affiliation,
    ): void {
        if (! $affiliation->isEnrolled()) {
            return;
        }

        // 異動時のみ他の在籍中を終了。オフなら兼任として複数の所属中を残す
        if ($request->boolean('close_others')) {
            $target->closeOtherEnrolledAffiliations($affiliation);
        }

        if (auth()->user()?->canEditCurrentAffiliationOrg()) {
            AffiliationHrDetailSync::syncHrDetailFromAffiliation($target->fresh(), $affiliation->fresh());
        }
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
