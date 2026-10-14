<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AssertsProfileAccess;
use App\Http\Requests\ScheduledAffiliationChangeRequest;
use App\Models\ScheduledAffiliationChange;
use App\Models\User;
use App\Support\ScheduledAffiliationChangeDiff;
use App\Support\UserRouteHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ScheduledAffiliationChangeController extends Controller
{
    use AssertsProfileAccess;

    public function create(User $user): View|RedirectResponse
    {
        $this->assertCanScheduleAffiliationChange();

        $pending = $user->pendingScheduledAffiliationChange();
        if ($pending !== null) {
            return redirect()
                ->route('scheduled-affiliations.edit', $pending)
                ->with('info', '未反映の事前登録があるため、編集画面を開きました。');
        }

        $user->load(['profile', 'affiliationHistories']);
        $current = $user->currentAffiliation();

        return view('scheduled-affiliation.create', [
            'user' => $user,
            'current' => $current,
            'formValues' => ScheduledAffiliationChangeDiff::formValues($current, null),
        ]);
    }

    public function store(ScheduledAffiliationChangeRequest $request, User $user): RedirectResponse|Response
    {
        $this->assertCanScheduleAffiliationChange();

        if ($user->pendingScheduledAffiliationChange() !== null) {
            return redirect()
                ->to(UserRouteHelper::route($user, 'profile.edit', 'users.profile.edit'))
                ->with('error', '未反映の事前登録が既にあります。先に編集または取消してください。');
        }

        $validated = $request->validated();
        $overrides = ScheduledAffiliationChangeDiff::overrides($user->currentAffiliation(), $validated);

        if ($overrides === []) {
            return back()
                ->withInput()
                ->withErrors(['effective_date' => '現行から変更する項目を1つ以上指定してください。']);
        }

        $user->scheduledAffiliationChanges()->create([
            ...ScheduledAffiliationChangeDiff::attributesForCreate($user->currentAffiliation(), $validated),
            'created_by' => auth()->id(),
            'status' => ScheduledAffiliationChange::STATUS_PENDING,
        ]);

        return $this->redirectToProfileEdit($user, '異動・役職の事前登録を保存しました。変更した項目だけが反映日に適用されます。');
    }

    public function edit(ScheduledAffiliationChange $schedule): View|RedirectResponse
    {
        $this->assertCanScheduleAffiliationChange();
        $this->assertScheduleIsPending($schedule);

        $schedule->load(['user.profile', 'user.affiliationHistories']);
        $current = $schedule->user->currentAffiliation();

        return view('scheduled-affiliation.edit', [
            'schedule' => $schedule,
            'user' => $schedule->user,
            'current' => $current,
            'formValues' => ScheduledAffiliationChangeDiff::formValues($current, $schedule),
        ]);
    }

    public function update(ScheduledAffiliationChangeRequest $request, ScheduledAffiliationChange $schedule): RedirectResponse|Response
    {
        $this->assertCanScheduleAffiliationChange();
        $this->assertScheduleIsPending($schedule);

        $validated = $request->validated();
        $current = $schedule->user->currentAffiliation();
        $overrides = ScheduledAffiliationChangeDiff::overrides($current, $validated);

        if ($overrides === []) {
            return back()
                ->withInput()
                ->withErrors(['effective_date' => '現行から変更する項目を1つ以上指定してください。']);
        }

        $schedule->update(ScheduledAffiliationChangeDiff::attributesForUpdate($current, $validated));

        return $this->redirectToProfileEdit($schedule->user, '異動・役職の事前登録を更新しました。変更した項目だけが反映日に適用されます。');
    }

    public function destroy(ScheduledAffiliationChange $schedule): Response
    {
        $this->assertCanScheduleAffiliationChange();
        $this->assertScheduleIsPending($schedule);

        $user = $schedule->user;
        $schedule->update([
            'status' => ScheduledAffiliationChange::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        return $this->redirectToProfileEdit($user, '異動・役職の事前登録を取り消しました。');
    }

    private function assertCanScheduleAffiliationChange(): void
    {
        if (! auth()->user()?->canScheduleAffiliationChange()) {
            abort(403);
        }
    }

    private function assertScheduleIsPending(ScheduledAffiliationChange $schedule): void
    {
        if (! $schedule->isPending()) {
            abort(404);
        }
    }
}
