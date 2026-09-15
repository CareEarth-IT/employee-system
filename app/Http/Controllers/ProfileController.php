<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AssertsProfileAccess;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\ProfilePhotoStorage;
use App\Support\UserRouteHelper;
use App\Support\AffiliationStartDateAlignment;
use App\Support\NationalityOptions;
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

        $editableProfileFields = $viewer->editableProfileFieldNames($target);

        $target->load(['profile', 'hrDetail', 'affiliationHistories']);

        return view('profile.show', [
            'user' => $target,
            'canEdit' => $editableProfileFields !== [],
            'editableProfileFields' => $editableProfileFields,
        ]);
    }

    public function edit(?User $user = null): View|RedirectResponse
    {
        $target = $user ?? auth()->user();

        if (! auth()->user()->canEditProfile($target)) {
            return redirect()->to(UserRouteHelper::route($target, 'profile.show', 'users.profile.show'));
        }

        $target->load(['profile', 'affiliationHistories']);

        $editableProfileFields = auth()->user()->editableProfileFieldNames($target);

        return view('profile.edit', [
            'user' => $target,
            'editableProfileFields' => $editableProfileFields,
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
        $allowedProfileFields = $viewer->editableProfileFieldNames($target);
        $validated = $request->validated();
        $data = collect($validated)->only($allowedProfileFields)->all();

        $identityData = [];
        if ($viewer->canEditEmployeeIdentity($target)) {
            if (array_key_exists('employee_id', $validated)) {
                $identityData['employee_id'] = trim((string) $validated['employee_id']);
            }
            if (array_key_exists('email', $validated)) {
                $identityData['email'] = strtolower(trim((string) $validated['email']));
            }
        }

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
            $previousJoinedAt = $profile->joined_at?->toDateString();
            $profile->update([
                ...$data,
                'import_locked' => true,
            ]);

            if (array_key_exists('joined_at', $data)
                && $profile->fresh()->joined_at?->toDateString() !== $previousJoinedAt) {
                $target->load('affiliationHistories');
                AffiliationStartDateAlignment::syncForUser($target);
            }

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

            $payload = [
                'message' => 'プロフィールを保存しました。',
                'fields' => $fields,
            ];

            if (array_key_exists('employee_id', $identityData)) {
                $payload['profile_urls'] = $this->profileUrls($target, $viewer);
            }

            return response()->json($payload);
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
            'nationality' => $this->inlineTextField(
                NationalityOptions::toDisplayName($profile->nationality) ?? $profile->nationality,
            ),
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

    /**
     * @return array{show: string, edit: string, update: string, hr_detail_edit: string, destroy: string}
     */
    private function profileUrls(User $target, User $viewer): array
    {
        $forceEdit = $viewer->shouldForceProfileEditMode($target);

        return [
            'show' => UserRouteHelper::route($target, 'profile.show', 'users.profile.show'),
            'edit' => UserRouteHelper::route($target, 'profile.edit', 'users.profile.edit'),
            'update' => UserRouteHelper::route($target, 'profile.update', 'users.profile.update'),
            'hr_detail_edit' => UserRouteHelper::route($target, 'profile.hr-detail.edit', 'users.profile.hr-detail.edit'),
            'destroy' => UserRouteHelper::isSelf($target)
                ? ''
                : route('users.profile.destroy', $target),
            'redirect' => UserRouteHelper::route(
                $target,
                $forceEdit ? 'profile.edit' : 'profile.show',
                $forceEdit ? 'users.profile.edit' : 'users.profile.show',
            ),
        ];
    }
}
