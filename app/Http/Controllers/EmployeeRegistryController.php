<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRegistryStoreRequest;
use App\Http\Requests\EmployeeRegistryUpdateRequest;
use App\Models\User;
use App\Services\EmployeeRegistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeRegistryController extends Controller
{
    public function __construct(
        private EmployeeRegistryService $registry,
    ) {}

    public function create(Request $request): View
    {
        abort_unless($request->user()?->canManageEmployeeRegistry(), 403);

        return view('employees.create');
    }

    public function store(EmployeeRegistryStoreRequest $request): RedirectResponse
    {
        $user = $this->registry->create($request->validated());

        return redirect()
            ->route('employees.create')
            ->with('success', '社員を登録しました。');
    }

    public function edit(Request $request, User $user): View
    {
        abort_unless($request->user()?->canManageEmployeeRegistry(), 403);

        $user->load(['profile', 'hrDetail', 'affiliationHistories']);

        return view('employees.edit', [
            'employee' => $user,
            'formValues' => $this->formValues($user),
        ]);
    }

    public function update(EmployeeRegistryUpdateRequest $request, User $user): RedirectResponse
    {
        $this->registry->update($user, $request->validated());

        return redirect()
            ->route('employees.edit', $user)
            ->with('success', '社員情報を更新しました。');
    }

    /**
     * @return array<string, string>
     */
    private function formValues(User $user): array
    {
        $affiliation = $user->currentAffiliation();
        $profile = $user->profile;
        $hrDetail = $user->hrDetail;

        return [
            'name' => old('name', $this->registry->displayName($user)),
            'email' => old('email', (string) $user->email),
            'employee_id' => old('employee_id', (string) ($user->employee_id ?? '')),
            'department' => old('department', (string) ($affiliation?->department ?? '')),
            'section' => old('section', (string) ($affiliation?->section ?? '')),
            'location' => old('location', (string) ($affiliation?->location ?? '')),
            'employment_type' => old(
                'employment_type',
                (string) ($hrDetail?->employment_type ?: $affiliation?->position ?: ''),
            ),
            'name_kana' => old('name_kana', (string) ($profile?->name_kana ?? '')),
            'english_name' => old('english_name', (string) ($profile?->english_name ?? '')),
            'abbreviated_name' => old('abbreviated_name', (string) ($profile?->abbreviated_name ?? '')),
            'gender' => old('gender', (string) ($hrDetail?->gender ?? '')),
            'nationality' => old('nationality', (string) ($profile?->nationality ?? '')),
            'joined_at' => old('joined_at', $profile?->joined_at?->format('Y-m-d') ?? ''),
            'remarks' => old('remarks', (string) ($hrDetail?->remarks ?? '')),
        ];
    }
}
