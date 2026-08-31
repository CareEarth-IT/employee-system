<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AssertsProfileAccess;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\ProfilePhotoStorage;
use App\Support\UserRouteHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProfileController extends Controller
{
    use AssertsProfileAccess;

    public function show(?User $user = null): View|RedirectResponse
    {
        $target = $user ?? auth()->user();
        $viewer = auth()->user();

        if ($viewer?->shouldForceProfileEditMode($target)) {
            return redirect()->to(UserRouteHelper::route($target, 'profile.edit', 'users.profile.edit'));
        }

        $canEdit = $viewer->canEditProfile($target);

        $target->load(['profile', 'hrDetail', 'affiliationHistories']);

        return view('profile.show', [
            'user' => $target,
            'canEdit' => $canEdit,
        ]);
    }

    public function edit(?User $user = null): View|RedirectResponse
    {
        $target = $user ?? auth()->user();

        if (! auth()->user()->canEditProfile($target)) {
            return redirect()->to(UserRouteHelper::route($target, 'profile.show', 'users.profile.show'));
        }

        $target->load(['profile', 'affiliationHistories']);

        return view('profile.edit', [
            'user' => $target,
            'canDeleteEmployee' => auth()->user()->isHrDepartment() && auth()->id() !== $target->id,
        ]);
    }

    public function destroy(User $user): RedirectResponse
    {
        if (! auth()->user()->isHrDepartment()) {
            abort(403);
        }

        if (auth()->id() === $user->id) {
            return redirect()
                ->to(UserRouteHelper::route($user, 'profile.edit', 'users.profile.edit'))
                ->with('error', '自分自身のアカウントは削除できません。');
        }

        $displayName = $user->displayName();

        DB::transaction(function () use ($user): void {
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        return redirect()
            ->route('employees.index')
            ->with('success', "{$displayName} さんの社員情報を削除しました。");
    }

    public function update(ProfileUpdateRequest $request, ?User $user = null): RedirectResponse|JsonResponse
    {
        $target = $user ?? auth()->user();
        $viewer = auth()->user();
        $data = $request->validated();

        $identityData = [];
        if ($viewer->canEditEmployeeIdentity($target)) {
            if (array_key_exists('employee_id', $data)) {
                $identityData['employee_id'] = trim((string) $data['employee_id']);
            }
            if (array_key_exists('email', $data)) {
                $identityData['email'] = strtolower(trim((string) $data['email']));
            }
        }
        unset($data['employee_id'], $data['email']);

        if ($identityData !== []) {
            $identityData['import_locked'] = true;
            $target->update($identityData);
            $target->refresh();
        }

        $profileTouched = $data !== [] || $request->hasFile('photo');
        if ($profileTouched) {
            $this->assertCanEditProfile($target);

            $profile = $target->profile()->firstOrCreate(['user_id' => $target->id]);

            if ($request->hasFile('photo')) {
                ProfilePhotoStorage::delete($profile->photo_path);
                $data['photo_path'] = ProfilePhotoStorage::store($request->file('photo'));
            }

            unset($data['photo']);
            $profile->update([
                ...$data,
                'import_locked' => true,
            ]);

            $this->syncUserNameFromProfile($target, $profile->fresh());
            $target->update(['import_locked' => true]);
        }

        if ($request->wantsJson()) {
            $profile = $target->profile;
            $fields = $profile
                ? $this->inlineFieldPayload($profile->fresh())
                : [];

            if (array_key_exists('employee_id', $identityData)) {
                $fields['employee_id'] = $this->inlineTextField($target->employee_id);
            }
            if (array_key_exists('email', $identityData)) {
                $fields['email'] = $this->inlineTextField($target->email);
            }

            return response()->json([
                'message' => 'プロフィールを保存しました。',
                'fields' => $fields,
            ]);
        }

        return redirect()
            ->to(UserRouteHelper::route(
                $target,
                $viewer->shouldForceProfileEditMode($target) ? 'profile.edit' : 'profile.show',
                $viewer->shouldForceProfileEditMode($target) ? 'users.profile.edit' : 'users.profile.show',
            ))
            ->with('success', 'プロフィールを保存しました。');
    }

    private function syncUserNameFromProfile(User $user, EmployeeProfile $profile): void
    {
        $displayName = $profile->name_kana ?: $profile->english_name;

        if (! $displayName) {
            return;
        }

        $user->update(['name' => $displayName]);
    }

    /**
     * @return array<string, array{value: string, display: string}>
     */
    private function inlineFieldPayload(EmployeeProfile $profile): array
    {
        return [
            'english_name' => $this->inlineTextField($profile->english_name),
            'name_kana' => $this->inlineTextField($profile->name_kana),
            'abbreviated_name' => $this->inlineTextField($profile->abbreviated_name),
            'joined_at' => [
                'value' => $profile->joined_at?->format('Y-m-d') ?? '',
                'display' => $profile->joined_at?->format('Y/m/d') ?? '—',
            ],
            'nationality' => $this->inlineTextField($profile->nationality),
            'languages' => $this->inlineTextField($profile->languages),
            'self_introduction' => $this->inlineTextField($profile->self_introduction),
        ];
    }

    /**
     * @return array{value: string, display: string}
     */
    private function inlineTextField(?string $value): array
    {
        $value = $value ?? '';

        return [
            'value' => $value,
            'display' => $value === '' ? '—' : $value,
        ];
    }
}
