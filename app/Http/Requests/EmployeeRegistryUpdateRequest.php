<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesEmployeeRegistryFields;
use App\Models\User;
use App\Support\RegistryOrgAssignment;
use Illuminate\Foundation\Http\FormRequest;

class EmployeeRegistryUpdateRequest extends FormRequest
{
    use ValidatesEmployeeRegistryFields;

    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageEmployeeRegistry();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');
        $currentAffiliation = $target->currentAffiliation();
        $currentOrg = $currentAffiliation
            ? RegistryOrgAssignment::splitForRegistryForm(
                $currentAffiliation->section,
                $currentAffiliation->department,
                $currentAffiliation->location,
            )
            : ['section' => null, 'team' => null];

        return [
            ...$this->registryFieldRules(
                uniqueIgnoreUserId: $target->id,
                currentDepartment: $currentAffiliation?->department,
                currentCompany: $currentAffiliation?->company,
                currentSection: $currentOrg['section'],
                currentTeam: $currentOrg['team'],
                currentEmploymentStatus: $target->hrDetail?->employment_status,
                splitSectionTeam: true,
            ),
            'password' => $this->registryPasswordRules(required: false),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->registryFieldMessages(),
            'password.confirmed' => 'パスワード（確認）が一致しません。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->registryFieldAttributes();
    }
}
